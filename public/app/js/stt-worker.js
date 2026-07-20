/* =====================================================================
 * stt-worker.js — Whisper ONNX exécuté dans le navigateur (thread dédié)
 * ---------------------------------------------------------------------
 * Portage du worker de transcription locale de la PWA de référence
 * (frontend-existant/stt.worker.js) : l'audio ne quitte jamais l'appareil
 * (R3, R5 — fonctionne hors ligne une fois le modèle téléchargé). Seule la
 * bibliothèque d'inférence (transformers.js) et les poids du modèle sont
 * chargés depuis un CDN — l'audio, lui, ne transite jamais par le réseau.
 * ===================================================================== */

import { pipeline, env } from 'https://cdn.jsdelivr.net/npm/@huggingface/transformers@3.3.3';

env.allowLocalModels = false;
env.useBrowserCache = true;

let transcriber = null;
let loadedId = null;
let cachedDevice = null;

async function detectDevice() {
  if (cachedDevice) return cachedDevice;
  try {
    if ('gpu' in navigator) {
      const adapter = await navigator.gpu.requestAdapter();
      if (adapter) {
        cachedDevice = 'webgpu';
        return cachedDevice;
      }
    }
  } catch (_) {
    // pas de WebGPU utilisable
  }
  cachedDevice = 'wasm';
  return cachedDevice;
}

function makeProgressAggregator(notify) {
  const files = new Map();
  let lastUpdate = Date.now();
  let stalled = false;
  let onTimeout = null;

  const STALL_WARN_MS = 15000;
  const STALL_ABORT_MS = 90000;

  const watchdog = setInterval(() => {
    const idle = Date.now() - lastUpdate;
    if (idle > STALL_ABORT_MS) {
      clearInterval(watchdog);
      if (onTimeout) onTimeout();
      return;
    }
    if (idle > STALL_WARN_MS && !stalled) {
      stalled = true;
      notify(`Connexion lente ou interrompue... nouvelle tentative automatique dans ${Math.round((STALL_ABORT_MS - idle) / 1000)}s si aucune réponse.`);
    }
  }, 3000);

  function report() {
    const list = [...files.values()];
    if (!list.length) return;
    const avg = list.reduce((s, f) => s + (f.progress || 0), 0) / list.length;
    const doneCount = list.filter((f) => f.done).length;
    notify(`Téléchargement du modèle ${Math.round(avg)} % (fichier ${Math.min(doneCount + 1, list.length)}/${list.length})`);
  }

  return {
    stop: () => clearInterval(watchdog),
    onStall: (fn) => { onTimeout = fn; },
    handle: (p) => {
      lastUpdate = Date.now();
      stalled = false;
      if (p.status === 'initiate' && p.file) {
        files.set(p.file, { progress: 0, done: false });
      } else if (p.status === 'progress' && p.file) {
        const f = files.get(p.file) || { progress: 0, done: false };
        f.progress = p.progress || 0;
        files.set(p.file, f);
        report();
      } else if (p.status === 'done' && p.file) {
        const f = files.get(p.file) || { progress: 100, done: false };
        f.progress = 100;
        f.done = true;
        files.set(p.file, f);
        report();
      } else if (p.status === 'ready') {
        notify('Modèle prêt.');
      }
    },
  };
}

async function getTranscriber(modelId, notify) {
  if (transcriber && loadedId === modelId) return transcriber;

  const device = await detectDevice();
  const opts = {
    device,
    dtype: device === 'webgpu'
      ? { encoder_model: 'fp16', decoder_model_merged: 'q4' }
      : { encoder_model: 'q8', decoder_model_merged: 'q8' },
  };

  notify(device === 'webgpu' ? 'Initialisation Whisper (WebGPU)...' : 'Initialisation Whisper (WASM)...');

  const agg = makeProgressAggregator(notify);
  opts.progress_callback = agg.handle;

  const timeout = new Promise((_, reject) => {
    agg.onStall(() => reject(new Error(
      'Téléchargement du modèle interrompu (réseau trop lent ou coupé). '
      + 'Réessayez sur une connexion stable, ou choisissez un modèle plus léger dans Paramètres.'
    )));
  });

  try {
    transcriber = await Promise.race([
      pipeline('automatic-speech-recognition', modelId, opts),
      timeout,
    ]);
  } finally {
    agg.stop();
  }

  loadedId = modelId;
  return transcriber;
}

const CHUNK_S = 30;
const SAMPLE_RATE = 16000;

function splitChunks(pcm) {
  const chunkLen = CHUNK_S * SAMPLE_RATE;
  if (pcm.length <= chunkLen) return [pcm];
  const chunks = [];
  for (let start = 0; start < pcm.length; start += chunkLen) {
    chunks.push(pcm.subarray(start, Math.min(start + chunkLen, pcm.length)));
  }
  return chunks;
}

self.addEventListener('message', async (e) => {
  const { id, model, lang, pcm, warmup } = e.data;
  const notify = (message) => self.postMessage({ id, type: 'progress', message });

  try {
    const asr = await getTranscriber(model, notify);
    if (warmup) {
      self.postMessage({ id, type: 'done', text: '' });
      return;
    }

    const chunks = splitChunks(pcm);
    const total = chunks.length;
    let text = '';

    for (let i = 0; i < chunks.length; i++) {
      notify(total > 1
        ? `Transcription en cours (local, hors ligne) — tronçon ${i + 1}/${total}...`
        : 'Transcription en cours (local, hors ligne)...');

      const out = await asr(chunks[i], {
        language: lang || 'fr',
        task: 'transcribe',
        return_timestamps: false,
      });
      text += (text ? ' ' : '') + (out?.text || '').trim();
    }

    self.postMessage({ id, type: 'done', text: text.trim() });
  } catch (err) {
    self.postMessage({ id, type: 'error', message: err?.message || String(err) });
  }
});
