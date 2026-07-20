/**
 * Abstraction de transcription vocale multi-fournisseur (F4/F11). Deux
 * moteurs, tous deux conformes à R3/R4 (contrairement à la PWA de référence,
 * qui pouvait envoyer l'audio directement à Groq/OpenAI avec une clé stockée
 * dans le navigateur) :
 *  - "server" : /api/v1/stt, proxy backend vers Groq — la clé reste côté
 *    serveur (R4).
 *  - "local"  : Whisper exécuté entièrement dans le navigateur (WebGPU/WASM,
 *    transformers.js) — l'audio ne quitte jamais l'appareil, fonctionne
 *    hors ligne une fois le modèle mis en cache (R5).
 */
import { Api } from './api.js';
import { MetaStore } from './db.js';

export const LOCAL_MODELS = [
  { id: 'onnx-community/whisper-base', label: 'Whisper base (~80 Mo, appareil modeste)' },
  { id: 'onnx-community/whisper-small', label: 'Whisper small (~250 Mo, recommandé FR)' },
  { id: 'onnx-community/whisper-large-v3-turbo', label: 'Whisper large-v3-turbo (~800 Mo, PC puissant)' },
];

export async function activeProvider() {
  return (await MetaStore.get('stt_provider')) || 'server';
}

export async function setProvider(provider) {
  await MetaStore.set('stt_provider', provider);
}

export async function activeLocalModel() {
  return (await MetaStore.get('stt_local_model')) || LOCAL_MODELS[1].id;
}

export async function setLocalModel(modelId) {
  await MetaStore.set('stt_local_model', modelId);
}

let _worker = null;
let _seq = 0;

function localWorker() {
  if (_worker) return _worker;
  _worker = new Worker(new URL('./stt-worker.js', import.meta.url), { type: 'module' });
  return _worker;
}

async function decodeTo16kMono(file) {
  const buf = await file.arrayBuffer();
  const Ctx = window.AudioContext || window.webkitAudioContext;
  const ctx = new Ctx({ sampleRate: 16000 });
  let decoded;

  try {
    decoded = await ctx.decodeAudioData(buf);
  } catch (_) {
    await ctx.close();
    throw new Error("format audio non décodable par ce navigateur (essayez Chrome/Edge, ou convertissez en .m4a/.mp3)");
  }

  let pcm;
  if (decoded.numberOfChannels === 1) {
    pcm = decoded.getChannelData(0);
  } else {
    const l = decoded.getChannelData(0);
    const r = decoded.getChannelData(1);
    pcm = new Float32Array(l.length);
    for (let i = 0; i < l.length; i++) pcm[i] = (l[i] + r[i]) / 2;
  }

  await ctx.close();
  return pcm;
}

async function transcribeLocal(file, onProgress) {
  onProgress('Décodage du vocal…');
  const pcm = await decodeTo16kMono(file);
  const model = await activeLocalModel();
  const w = localWorker();
  const id = ++_seq;

  return new Promise((resolve, reject) => {
    const onMessage = (e) => {
      const m = e.data;
      if (m.id !== id) return;
      if (m.type === 'progress') {
        onProgress(m.message);
        return;
      }
      w.removeEventListener('message', onMessage);
      if (m.type === 'error') reject(new Error(m.message));
      else resolve((m.text || '').trim());
    };
    w.addEventListener('message', onMessage);
    w.postMessage({ id, model, lang: 'fr', pcm }, [pcm.buffer]);
  });
}

/**
 * @returns {Promise<{text: string, provider: string}>}
 */
export async function transcribeAudio(file, onProgress = () => {}) {
  const provider = await activeProvider();

  if (provider === 'local') {
    const text = await transcribeLocal(file, onProgress);
    return { text, provider: 'local' };
  }

  onProgress('Transcription en cours (serveur)…');
  const result = await Api.transcribe(file, file.name);
  return { text: result.text, provider: 'server' };
}

export async function warmupLocalModel(onProgress = () => {}) {
  const model = await activeLocalModel();
  const w = localWorker();
  const id = ++_seq;

  return new Promise((resolve, reject) => {
    w.addEventListener('message', function handler(e) {
      if (e.data.id !== id) return;
      if (e.data.type === 'progress') {
        onProgress(e.data.message);
        return;
      }
      w.removeEventListener('message', handler);
      if (e.data.type === 'error') reject(new Error(e.data.message));
      else resolve();
    });
    w.postMessage({ id, model, warmup: true });
  });
}
