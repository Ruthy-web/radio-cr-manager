import { ReportsStore, CatalogStore, MetaStore } from './db.js';
import { Api, setUnauthorizedHandler } from './api.js';
import { semanticInsert } from './semantic.js';
import { renderIcons } from './icons.js';
import {
  transcribeAudio,
  activeProvider,
  setProvider,
  activeLocalModel,
  setLocalModel,
  warmupLocalModel,
  LOCAL_MODELS,
} from './stt.js';
import { renderMarkdown } from './markdown.js';

const $ = (id) => document.getElementById(id);

const state = {
  user: null,
  hospitals: [],
  report: null,
  online: navigator.onLine,
  recognition: null,
  listening: false,
  audioFile: null,
  bulletinFile: null,
  assistantMessages: [],
  deferredInstallPrompt: null,
  heartbeatTimer: null,
  syncTimer: null,
};

/* ------------------------------------------------------------ BOOT */

async function boot() {
  setUnauthorizedHandler(handleUnauthorized);
  window.addEventListener('online', () => setNetwork(true));
  window.addEventListener('offline', () => setNetwork(false));
  setNetwork(navigator.onLine);
  setupInstallPrompt();
  renderIcons();

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/app/service-worker.js').catch(() => {});
  }

  const token = await MetaStore.get('token');
  const user = await MetaStore.get('user');

  if (token && user) {
    await enterApp(user);
  } else {
    setupLoginForm();
  }
}

function handleUnauthorized() {
  MetaStore.remove('token');
  MetaStore.remove('user');
  stopBackgroundTasks();
  $('appShell').hidden = true;
  $('loginView').hidden = false;
  $('loginStatus').textContent = 'Session expirée, reconnectez-vous.';
}

/* ------------------------------------------------------------ AUTH */

function setupLoginForm() {
  $('loginView').hidden = false;
  $('appShell').hidden = true;

  $('loginForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    $('loginStatus').textContent = '';

    try {
      const data = await Api.login($('loginEmail').value.trim(), $('loginPassword').value);

      if (data.two_factor_required) {
        $('loginForm').hidden = true;
        $('twoFactorForm').hidden = false;
        $('twoFactorForm').dataset.challengeToken = data.challenge_token;
        return;
      }

      await MetaStore.set('token', data.token);
      await MetaStore.set('user', data.user);
      await enterApp(data.user);
    } catch (error) {
      $('loginStatus').textContent = error.message;
    }
  });

  $('twoFactorForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    $('twoFactorStatus').textContent = '';

    try {
      const challengeToken = $('twoFactorForm').dataset.challengeToken;
      const data = await Api.verifyTwoFactor(challengeToken, $('twoFactorCode').value.trim());
      await MetaStore.set('token', data.token);
      await MetaStore.set('user', data.user);
      await enterApp(data.user);
    } catch (error) {
      $('twoFactorStatus').textContent = error.message;
    }
  });
}

async function enterApp(user) {
  state.user = user;
  $('loginView').hidden = true;
  $('appShell').hidden = false;
  $('userLabel').textContent = user.name;
  $('settingsUser').textContent = `${user.name} (${user.email})`;

  setupNavigation();
  setupWorkspace();
  setupBulletin();
  setupAssistant();
  setupTemplates();
  setupHistory();
  setupSettings();
  setupSpeechRecognition();
  await setupSttSettings();

  await loadCatalogWithFallback();
  await renderHistory();
  await refreshSyncLabel();
  newReport();
  renderChatThread();

  startBackgroundTasks();
  renderIcons();

  if (state.online) syncNow(true);
}

async function logout() {
  try {
    await Api.logout();
  } catch (_) {
    // Hors ligne ou jeton déjà expiré : on nettoie quand même localement.
  }

  await MetaStore.remove('token');
  await MetaStore.remove('user');
  stopBackgroundTasks();
  location.reload();
}

/* ------------------------------------------------------------ NETWORK / BACKGROUND */

function setNetwork(isOnline) {
  state.online = isOnline;
  const dot = $('networkDot');
  const label = $('networkLabel');
  if (!dot || !label) return;

  dot.classList.toggle('online', isOnline);
  dot.classList.toggle('offline', !isOnline);
  label.textContent = isOnline ? 'En ligne' : 'Hors ligne';

  if (isOnline && state.user) syncNow();
}

function startBackgroundTasks() {
  state.heartbeatTimer = setInterval(() => {
    if (state.online) Api.heartbeat().catch(() => {});
  }, 5 * 60 * 1000);

  state.syncTimer = setInterval(() => {
    if (state.online) syncNow();
  }, 2 * 60 * 1000);
}

function stopBackgroundTasks() {
  clearInterval(state.heartbeatTimer);
  clearInterval(state.syncTimer);
}

/* ------------------------------------------------------------ INSTALLATION PWA */

function setupInstallPrompt() {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    state.deferredInstallPrompt = event;
    $('installBtn').hidden = false;
  });

  $('installBtn').addEventListener('click', async () => {
    if (!state.deferredInstallPrompt) return;
    state.deferredInstallPrompt.prompt();
    await state.deferredInstallPrompt.userChoice;
    state.deferredInstallPrompt = null;
    $('installBtn').hidden = true;
  });

  window.addEventListener('appinstalled', () => {
    $('installBtn').hidden = true;
  });
}

/* ------------------------------------------------------------ NAVIGATION */

function setupNavigation() {
  document.querySelectorAll('.nav-item').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.nav-item').forEach((b) => b.classList.remove('active'));
      document.querySelectorAll('.view').forEach((v) => v.classList.remove('active'));
      btn.classList.add('active');
      $(`${btn.dataset.view}View`).classList.add('active');

      if (btn.dataset.view === 'history') renderHistory();
      if (btn.dataset.view === 'settings') refreshSyncLabel();
      if (btn.dataset.view === 'templates') renderTemplateList();
      if (btn.dataset.view === 'assistant') $('chatInput').focus();
    });
  });
}

/* ------------------------------------------------------------ CATALOG */

async function loadCatalogWithFallback() {
  if (state.online) {
    try {
      const data = await Api.catalog();
      state.hospitals = data.hospitals;
      await CatalogStore.save(data.hospitals);
    } catch (_) {
      state.hospitals = await CatalogStore.load();
    }
  } else {
    state.hospitals = await CatalogStore.load();
  }

  hydrateHospitalSelect();
  renderTemplateList();
}

function hydrateHospitalSelect() {
  const select = $('hospitalSelect');
  select.innerHTML = state.hospitals
    .map((h) => `<option value="${h.id}">${escapeHtml(h.name)}</option>`)
    .join('');
  hydrateExamSelect();
}

function selectedHospital() {
  return state.hospitals.find((h) => String(h.id) === $('hospitalSelect').value) || null;
}

function selectedExam() {
  const hospital = selectedHospital();
  if (!hospital) return null;
  return hospital.exam_templates.find((e) => String(e.id) === $('examSelect').value) || null;
}

function hydrateExamSelect() {
  const hospital = selectedHospital();
  const select = $('examSelect');
  select.innerHTML = (hospital ? hospital.exam_templates : [])
    .map((e) => `<option value="${e.id}">${escapeHtml(e.title)}</option>`)
    .join('');
  toggleSideField();
}

function toggleSideField() {
  const exam = selectedExam();
  $('sideField').hidden = !exam || !exam.requires_side;
}

/* ------------------------------------------------------------ WORKSPACE */

function setupWorkspace() {
  $('hospitalSelect').addEventListener('change', hydrateExamSelect);
  $('examSelect').addEventListener('change', toggleSideField);

  $('resetBtn').addEventListener('click', newReport);
  $('saveBtn').addEventListener('click', () => saveCurrentReport(true));

  $('addResultBtn').addEventListener('click', () => {
    state.report.content.results.push({ text: '', abnormal: false, heading: false });
    renderResults();
  });

  $('voiceBtn').addEventListener('click', toggleVoiceDictation);
  $('stopVoiceBtn').addEventListener('click', stopVoiceDictation);
  $('audioFileInput').addEventListener('change', (event) => {
    state.audioFile = event.target.files?.[0] || null;
    $('audioName') && ($('audioName').textContent = state.audioFile ? state.audioFile.name : '');
  });
  $('transcribeBtn').addEventListener('click', transcribeImportedAudio);
  $('applyVoiceBtn').addEventListener('click', applyDictation);

  $('aiRefineBtn').addEventListener('click', aiRefineDictation);
  $('aiDraftBtn').addEventListener('click', aiGenerateDraft);
}

function newReport() {
  state.report = {
    client_uuid: crypto.randomUUID(),
    id: null,
    hospital_id: null,
    exam_template_id: null,
    patient_name: '',
    patient_age: '',
    patient_sex: '',
    file_number: '',
    prescriber: '',
    exam_date: todayIso(),
    content: { heading: '', technique: '', results: [], conclusion: '' },
    status: 'brouillon',
    dirty: false,
  };

  $('patientNameInput').value = '';
  $('patientAgeInput').value = '';
  $('patientSexInput').value = '';
  $('fileNumberInput').value = '';
  $('prescriberInput').value = '';
  $('dateInput').value = todayIso();
  $('sideSelect').value = '';
  $('voiceText').value = '';
  $('techniqueInput').value = '';
  $('conclusionInput').value = '';
  $('pageTitle').textContent = 'Nouveau dossier';

  resetBulletinPanel();
  hydrateFromExamTemplate();
  renderResults();
  runStatusChecks();
}

function hydrateFromExamTemplate() {
  const exam = selectedExam();
  if (!exam) return;

  state.report.exam_template_id = exam.id;
  state.report.content.heading = exam.heading;
  $('techniqueInput').value = exam.technique || '';
  state.report.content.results = (exam.results || []).map((r) => ({ ...r }));
  $('conclusionInput').value = exam.conclusion || '';
}

function todayIso() {
  return new Date().toISOString().slice(0, 10);
}

function renderResults() {
  const container = $('resultsList');
  const results = state.report.content.results;

  container.innerHTML = results
    .map(
      (r, index) => `
    <div class="result-row ${r.abnormal ? 'abnormal' : ''}" data-index="${index}">
      <textarea rows="1" data-field="text">${escapeHtml(r.text)}</textarea>
      <div class="result-controls">
        <button type="button" class="result-toggle ${r.abnormal ? 'active' : ''}" data-action="toggle" title="Marquer anormal">!</button>
        <button type="button" class="result-remove" data-action="remove" title="Supprimer">✕</button>
      </div>
    </div>`
    )
    .join('');

  container.querySelectorAll('.result-row').forEach((row) => {
    const index = Number(row.dataset.index);

    row.querySelector('[data-field="text"]').addEventListener('input', (e) => {
      results[index].text = e.target.value;
    });
    row.querySelector('[data-action="toggle"]').addEventListener('click', () => {
      results[index].abnormal = !results[index].abnormal;
      renderResults();
    });
    row.querySelector('[data-action="remove"]').addEventListener('click', () => {
      results.splice(index, 1);
      renderResults();
    });
  });
}

function collectReportFromForm() {
  const r = state.report;
  r.hospital_id = selectedHospital()?.id ?? null;
  r.exam_template_id = selectedExam()?.id ?? null;
  r.patient_name = $('patientNameInput').value.trim();
  r.patient_age = $('patientAgeInput').value.trim();
  r.patient_sex = $('patientSexInput').value;
  r.file_number = $('fileNumberInput').value.trim();
  r.prescriber = $('prescriberInput').value.trim();
  r.exam_date = $('dateInput').value || todayIso();
  r.content.technique = $('techniqueInput').value;
  r.content.conclusion = $('conclusionInput').value;
  r.content.identity = { side: $('sideSelect').value || null };
  return r;
}

async function saveCurrentReport(showFeedback) {
  const report = collectReportFromForm();

  if (!report.hospital_id) {
    if (showFeedback) alert("Sélectionnez un hôpital avant d'enregistrer.");
    return;
  }

  report.dirty = true;
  report.updated_at = new Date().toISOString();
  await ReportsStore.put(report);
  await renderHistory();
  runStatusChecks();

  if (showFeedback) {
    $('pageTitle').textContent = `${report.patient_name || 'Sans nom'} — enregistré localement`;
  }

  if (state.online) syncNow();
}

function runStatusChecks() {
  const report = state.report;
  const checks = [];

  checks.push(report.hospital_id ? ['ok', 'Hôpital et examen sélectionnés.'] : ['warn', 'Sélectionnez un hôpital et un examen.']);
  checks.push(report.patient_name ? ['ok', 'Identité patient renseignée.'] : ['warn', "Nom du patient manquant (laissez vide si inconnu — R2)."]);
  checks.push(report.content.results.length ? ['ok', `${report.content.results.length} ligne(s) de résultats.`] : ['warn', 'Aucun résultat.']);
  checks.push(report.dirty ? ['warn', 'Modifications non synchronisées.'] : ['ok', 'Synchronisé avec le serveur.']);

  $('statusChecks').innerHTML = checks
    .map(([kind, text]) => `<div class="check-item ${kind}">${escapeHtml(text)}</div>`)
    .join('');
}

/* ------------------------------------------------------------ BULLETIN PATIENT (LECTURE IA VISION) */

function setupBulletin() {
  $('bulletinInput').addEventListener('change', (event) => {
    const file = event.target.files?.[0] || null;
    state.bulletinFile = file;
    const preview = $('bulletinPreview');

    if (file && file.type.startsWith('image/')) {
      preview.src = URL.createObjectURL(file);
      preview.hidden = false;
    } else {
      preview.hidden = true;
      preview.removeAttribute('src');
    }

    $('bulletinStatus').textContent = file
      ? `${file.name} — prêt, cliquez sur « Lire le bulletin ».`
      : "Nécessite une connexion. Aucune donnée n'est inventée (R2).";
  });

  $('readBulletinBtn').addEventListener('click', readBulletin);
  $('extractTextBtn').addEventListener('click', extractFromPastedText);
}

function resetBulletinPanel() {
  state.bulletinFile = null;
  $('bulletinInput').value = '';
  $('bulletinPreview').hidden = true;
  $('bulletinPreview').removeAttribute('src');
  $('ocrText').value = '';
  $('bulletinStatus').textContent = "Nécessite une connexion. Aucune donnée n'est inventée (R2).";
}

async function readBulletin() {
  if (!state.bulletinFile) {
    $('bulletinStatus').textContent = "Ajoutez d'abord une photo ou un PDF du bulletin.";
    return;
  }
  if (!state.online) {
    $('bulletinStatus').textContent = 'La lecture automatique nécessite une connexion.';
    return;
  }

  $('bulletinStatus').textContent = 'Lecture en cours (IA vision)…';
  $('readBulletinBtn').disabled = true;

  try {
    const data = await Api.bulletin(state.bulletinFile);
    applyBulletinFields(data);
    $('bulletinStatus').textContent = "Champs préremplis depuis le bulletin — vérifiez avant d'enregistrer (R2).";
  } catch (error) {
    $('bulletinStatus').textContent = `Lecture impossible : ${error.message}`;
  } finally {
    $('readBulletinBtn').disabled = false;
  }
}

function extractFromPastedText() {
  const text = $('ocrText').value.trim();
  if (!text) return;

  applyBulletinFields(parseBulletinText(text));
  $('bulletinStatus').textContent = "Champs extraits du texte collé — vérifiez avant d'enregistrer (R2).";
}

function applyBulletinFields(data) {
  const fullName = [data.lastName, data.firstName].filter(Boolean).join(' ').trim() || data.name;
  if (fullName) $('patientNameInput').value = fullName;

  if (data.age) {
    $('patientAgeInput').value = data.age;
  } else if (data.dob) {
    const computed = computeAgeFromDob(data.dob);
    $('patientAgeInput').value = computed || data.dob;
  }

  if (data.sex) {
    const s = String(data.sex).trim().toLowerCase();
    if (s.startsWith('m')) $('patientSexInput').value = 'M';
    else if (s.startsWith('f')) $('patientSexInput').value = 'F';
  }

  if (data.record) $('fileNumberInput').value = data.record;
  if (data.doctor) $('prescriberInput').value = data.doctor;

  if (data.exam) {
    const hospital = selectedHospital();
    const needle = data.exam.toLowerCase();
    const match = hospital?.exam_templates.find(
      (e) => e.title.toLowerCase().includes(needle) || needle.includes(e.title.toLowerCase())
    );

    if (match) {
      $('examSelect').value = match.id;
      toggleSideField();
      hydrateFromExamTemplate();
      renderResults();
    }
  }

  if (data.side) {
    const sideMap = { droit: 'Droit', gauche: 'Gauche', bilateral: 'Bilatéral', bilatéral: 'Bilatéral' };
    const normalized = sideMap[data.side.toLowerCase()] || data.side;
    if ([...$('sideSelect').options].some((o) => o.value === normalized)) {
      $('sideSelect').value = normalized;
    }
  }

  runStatusChecks();
}

function computeAgeFromDob(dob) {
  const match = String(dob).match(/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{2,4})/);
  if (!match) return null;

  let [, day, month, year] = match;
  if (year.length === 2) year = (Number(year) > 30 ? '19' : '20') + year;

  const birth = new Date(Number(year), Number(month) - 1, Number(day));
  if (Number.isNaN(birth.getTime())) return null;

  const today = new Date();
  let age = today.getFullYear() - birth.getFullYear();
  const monthDiff = today.getMonth() - birth.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) age--;

  return age >= 0 && age < 130 ? String(age) : null;
}

function parseBulletinText(text) {
  const result = {};

  const nameMatch = text.match(/(?:M(?:me|r|lle)?\.?\s+)([A-ZÉÈÀÂÎÔÛÇ][\wÀ-ÿ'-]*(?:\s+[A-ZÉÈÀÂÎÔÛÇ][\wÀ-ÿ'-]*)*)/);
  if (nameMatch) result.name = nameMatch[1].trim();

  const ageMatch = text.match(/(\d{1,3})\s*an/i);
  if (ageMatch) result.age = ageMatch[1];

  const sexMatch = text.match(/\b(masculin|féminin|homme|femme)\b/i);
  if (sexMatch) result.sex = /^m/i.test(sexMatch[1]) ? 'M' : 'F';

  const doctorMatch = text.match(/(?:Dr\.?|Docteur)\s+([A-ZÉÈÀÂÎÔÛÇ][\wÀ-ÿ'-]+(?:\s+[A-ZÉÈÀÂÎÔÛÇ][\wÀ-ÿ'-]+)*)/);
  if (doctorMatch) result.doctor = `Dr ${doctorMatch[1].trim()}`;

  const recordMatch = text.match(/(?:dossier|n[°ºo]\s*dossier|matricule)\s*[:#]?\s*([\w-]+)/i);
  if (recordMatch) result.record = recordMatch[1];

  const examMatch = text.match(/(?:examen|exam)\s*[:#]?\s*([^,.;\n]+)/i);
  if (examMatch) result.exam = examMatch[1].trim();

  return result;
}

/* ------------------------------------------------------------ DICTATION */

function setupSpeechRecognition() {
  const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

  if (!SpeechRecognition) {
    $('voiceStatus').textContent = 'Dictée micro non disponible sur ce navigateur.';
    $('voiceBtn').disabled = true;
    return;
  }

  state.recognition = new SpeechRecognition();
  state.recognition.lang = 'fr-FR';
  state.recognition.continuous = true;
  state.recognition.interimResults = false;

  state.recognition.onresult = (event) => {
    let transcript = '';
    for (let i = event.resultIndex; i < event.results.length; i++) {
      transcript += event.results[i][0].transcript;
    }
    $('voiceText').value = ($('voiceText').value.trim() + ' ' + transcript).trim();
  };

  state.recognition.onerror = (event) => {
    state.listening = false;
    updateVoiceButtons();
    $('voiceStatus').textContent = event.error === 'not-allowed' ? 'Micro bloqué par le navigateur.' : `Erreur dictée : ${event.error}`;
  };

  state.recognition.onend = () => {
    state.listening = false;
    updateVoiceButtons();
  };

  $('voiceStatus').textContent = 'Prêt à dicter.';
}

function updateVoiceButtons() {
  $('voiceBtn').hidden = state.listening;
  $('stopVoiceBtn').hidden = !state.listening;
}

function toggleVoiceDictation() {
  if (!state.recognition) return;
  state.listening = true;
  updateVoiceButtons();
  $('voiceStatus').textContent = 'Dictée en cours…';
  try {
    state.recognition.start();
  } catch (_) {
    // déjà démarré
  }
}

function stopVoiceDictation() {
  state.recognition?.stop();
}

async function transcribeImportedAudio() {
  if (!state.audioFile) {
    $('voiceStatus').textContent = "Importez d'abord un fichier audio.";
    return;
  }

  const provider = await activeProvider();
  if (provider === 'server' && !state.online) {
    $('voiceStatus').textContent = 'Transcription serveur impossible hors ligne (passez en local dans Paramètres).';
    return;
  }

  $('transcribeBtn').disabled = true;
  $('voiceStatus').textContent = 'Transcription en cours…';

  try {
    const { text } = await transcribeAudio(state.audioFile, (message) => {
      $('voiceStatus').textContent = message;
    });
    $('voiceText').value = ($('voiceText').value.trim() + ' ' + text).trim();
    $('voiceStatus').textContent = provider === 'local' ? 'Vocal transcrit localement (hors ligne).' : 'Vocal transcrit.';
  } catch (error) {
    $('voiceStatus').textContent = `Transcription impossible : ${error.message}`;
  } finally {
    $('transcribeBtn').disabled = false;
  }
}

function applyDictation() {
  const dictation = $('voiceText').value.trim();
  if (!dictation) return;

  const { results, conclusion, replaced, added } = semanticInsert(
    dictation,
    state.report.content.results,
    $('conclusionInput').value
  );

  state.report.content.results = results;
  $('conclusionInput').value = conclusion || '';
  renderResults();
  runStatusChecks();
  $('voiceStatus').textContent = `Intégré : ${replaced} ligne(s) modifiée(s), ${added} ajoutée(s).`;
}

async function aiRefineDictation() {
  const dictation = $('voiceText').value.trim();

  if (!dictation) {
    $('aiStatus').textContent = 'Dictez ou saisissez du texte avant de raffiner.';
    return;
  }
  if (!state.online) {
    $('aiStatus').textContent = 'Le raffinage IA nécessite une connexion.';
    return;
  }

  $('aiStatus').textContent = 'Raffinage en cours…';

  try {
    const resultsAsText = state.report.content.results.map((r) => r.text);
    const data = await Api.refine(resultsAsText, $('conclusionInput').value, dictation);

    state.report.content.results = data.results.map((text, index) => ({
      text,
      abnormal: state.report.content.results[index]?.abnormal ?? false,
      heading: state.report.content.results[index]?.heading ?? false,
    }));
    $('conclusionInput').value = data.conclusion || $('conclusionInput').value;
    renderResults();
    $('aiStatus').textContent = 'Raffinage appliqué. À valider.';
  } catch (error) {
    $('aiStatus').textContent = `Raffinage impossible : ${error.message}`;
  }
}

async function aiGenerateDraft() {
  const prompt = $('aiPromptText').value.trim();

  if (!prompt) {
    $('aiStatus').textContent = 'Décrivez la demande avant de générer.';
    return;
  }
  if (!state.online) {
    $('aiStatus').textContent = 'La génération IA nécessite une connexion.';
    return;
  }

  const hospital = selectedHospital();
  if (!hospital) {
    $('aiStatus').textContent = "Sélectionnez d'abord un hôpital.";
    return;
  }

  $('aiStatus').textContent = 'Génération en cours…';

  try {
    const data = await Api.draft({
      prompt,
      hospital_id: hospital.id,
      exam_template_id: selectedExam()?.id ?? null,
      patient: {
        age: $('patientAgeInput').value.trim() || null,
        sex: $('patientSexInput').value || null,
        side: $('sideSelect').value || null,
      },
    });

    $('techniqueInput').value = data.technique || '';
    $('conclusionInput').value = data.conclusion || '';
    state.report.content.results = (data.results || []).map((text) => ({ text, abnormal: false, heading: false }));
    renderResults();
    $('aiStatus').textContent = 'Compte rendu généré par IA. À valider médicalement.';
  } catch (error) {
    $('aiStatus').textContent = `Génération impossible : ${error.message}`;
  }
}

/* ------------------------------------------------------------ ASSISTANT IA (CHAT) */

function setupAssistant() {
  $('chatSendBtn').addEventListener('click', sendChatMessage);
  $('chatInput').addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      sendChatMessage();
    }
  });

  $('assistantClearBtn').addEventListener('click', () => {
    state.assistantMessages = [];
    $('assistantStatus').textContent = '';
    $('assistantStatus').classList.remove('error');
    renderChatThread();
  });

  $('assistantSuggestions').querySelectorAll('.chip').forEach((chip) => {
    chip.addEventListener('click', () => {
      $('chatInput').value = chip.textContent.trim();
      sendChatMessage();
    });
  });
}

async function sendChatMessage() {
  const text = $('chatInput').value.trim();
  if (!text) return;

  if (!state.online) {
    $('assistantStatus').textContent = "L'assistant nécessite une connexion.";
    $('assistantStatus').classList.add('error');
    return;
  }

  state.assistantMessages.push({ role: 'user', content: text });
  $('chatInput').value = '';
  $('assistantStatus').textContent = '';
  $('assistantStatus').classList.remove('error');
  $('chatSendBtn').disabled = true;
  renderChatThread(true);

  try {
    const useWeb = $('assistantWebToggle').checked;
    const payload = state.assistantMessages.map(({ role, content }) => ({ role, content }));
    const data = await Api.chat(payload, useWeb);
    state.assistantMessages.push({ role: 'assistant', content: data.text, sources: data.sources || [] });
  } catch (error) {
    state.assistantMessages.push({ role: 'assistant', content: `Erreur : ${error.message}`, sources: [] });
    $('assistantStatus').textContent = error.message;
    $('assistantStatus').classList.add('error');
  } finally {
    $('chatSendBtn').disabled = false;
    renderChatThread();
  }
}

function renderChatThread(typing = false) {
  const container = $('chatThread');

  if (!state.assistantMessages.length && !typing) {
    container.innerHTML = '<div class="chat-empty" id="chatEmpty">Aucune conversation pour l\'instant. Posez votre première question ci-dessous.</div>';
    return;
  }

  const bubbles = state.assistantMessages
    .map((m) => {
      const sourcesHtml = m.sources && m.sources.length
        ? `<div class="chat-sources"><strong>Sources</strong>${m.sources
            .map((s) => `<a href="${escapeHtml(s.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(s.title || s.url)}</a>`)
            .join('')}</div>`
        : '';

      return `
      <div class="chat-msg ${m.role}">
        <span class="chat-role">${m.role === 'user' ? 'Vous' : 'Assistant'}</span>
        <div class="chat-bubble">${renderMarkdown(m.content)}${sourcesHtml}</div>
      </div>`;
    })
    .join('');

  const typingHtml = typing
    ? '<div class="chat-msg assistant"><span class="chat-role">Assistant</span><div class="chat-bubble"><div class="chat-typing"><span></span><span></span><span></span></div></div></div>'
    : '';

  container.innerHTML = bubbles + typingHtml;
  container.scrollTop = container.scrollHeight;
}

/* ------------------------------------------------------------ MODÈLES (BIBLIOTHÈQUE) */

function setupTemplates() {
  $('templateSearch').addEventListener('input', renderTemplateList);
}

function renderTemplateList() {
  const container = $('templateList');
  if (!container) return;

  const query = $('templateSearch').value.trim().toLowerCase();

  const groups = state.hospitals
    .map((hospital) => ({
      hospital,
      exams: hospital.exam_templates.filter((e) => !query || e.title.toLowerCase().includes(query)),
    }))
    .filter((g) => g.exams.length);

  if (!groups.length) {
    container.innerHTML = '<div class="history-card">Aucun modèle ne correspond à la recherche.</div>';
    return;
  }

  container.innerHTML = groups
    .map(
      (g) => `
    <div class="template-hospital">
      <div class="template-hospital-head">
        <span class="dot" style="background:${escapeHtml(g.hospital.colors?.primary || '#0f766e')}"></span>
        <strong>${escapeHtml(g.hospital.name)}</strong>
        <span>${g.exams.length} examen(s)</span>
      </div>
      <div class="template-grid">
        ${g.exams
          .map(
            (e) => `
          <div class="template-card" style="cursor:pointer" data-hospital="${g.hospital.id}" data-exam="${e.id}">
            <strong>${escapeHtml(e.title)}</strong>
            <span>${e.requires_side ? 'Latéralité requise' : escapeHtml(e.modality || 'Modèle')}</span>
          </div>`
          )
          .join('')}
      </div>
    </div>`
    )
    .join('');

  container.querySelectorAll('.template-card').forEach((card) => {
    card.addEventListener('click', () => {
      $('hospitalSelect').value = card.dataset.hospital;
      hydrateExamSelect();
      $('examSelect').value = card.dataset.exam;
      toggleSideField();
      hydrateFromExamTemplate();
      renderResults();
      runStatusChecks();
      document.querySelector('[data-view="workspace"]').click();
    });
  });
}

/* ------------------------------------------------------------ HISTORY */

function setupHistory() {
  $('exportHistoryBtn').addEventListener('click', exportHistory);
  $('importHistoryInput').addEventListener('change', importHistory);
}

async function renderHistory() {
  const reports = await ReportsStore.all();
  const container = $('historyList');

  if (!reports.length) {
    container.innerHTML = '<div class="history-card">Aucun compte rendu enregistré sur cet appareil.</div>';
    return;
  }

  const byDay = new Map();
  reports
    .sort((a, b) => (b.exam_date || '').localeCompare(a.exam_date || ''))
    .forEach((r) => {
      const key = r.exam_date || 'sans-date';
      if (!byDay.has(key)) byDay.set(key, []);
      byDay.get(key).push(r);
    });

  container.innerHTML = [...byDay.entries()]
    .map(
      ([day, list]) => `
    <div>
      <div class="history-day-head">
        <strong>${escapeHtml(dayLabel(day))}</strong>
        <span>${list.length} compte(s) rendu(s)</span>
      </div>
      ${list
        .map(
          (r) => `
        <div class="history-card" data-uuid="${r.client_uuid}">
          <div class="meta">
            <strong>${escapeHtml(r.patient_name || 'Patient sans nom')}</strong>
            <span>${escapeHtml(hospitalName(r.hospital_id))} — ${escapeHtml(examTitle(r))}</span>
          </div>
          <span class="badge ${r.dirty ? 'dirty' : 'synced'}">${r.dirty ? 'Non synchronisé' : 'Synchronisé'}</span>
          <button class="btn secondary small" data-action="open">Rouvrir</button>
        </div>`
        )
        .join('')}
    </div>`
    )
    .join('');

  container.querySelectorAll('[data-action="open"]').forEach((btn) => {
    btn.addEventListener('click', () => openReport(btn.closest('.history-card').dataset.uuid));
  });
}

function hospitalName(id) {
  return state.hospitals.find((h) => h.id === id)?.name || '—';
}

function examTitle(report) {
  const hospital = state.hospitals.find((h) => h.id === report.hospital_id);
  return hospital?.exam_templates.find((e) => e.id === report.exam_template_id)?.title || '—';
}

function dayLabel(key) {
  if (key === 'sans-date') return 'Sans date';
  const d = new Date(`${key}T12:00:00`);
  return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}

async function openReport(clientUuid) {
  const report = await ReportsStore.get(clientUuid);
  if (!report) return;

  state.report = report;
  $('hospitalSelect').value = report.hospital_id;
  hydrateExamSelect();
  $('examSelect').value = report.exam_template_id;
  toggleSideField();
  $('sideSelect').value = report.content.identity?.side || '';
  $('dateInput').value = report.exam_date || todayIso();
  $('patientNameInput').value = report.patient_name || '';
  $('patientAgeInput').value = report.patient_age || '';
  $('patientSexInput').value = report.patient_sex || '';
  $('fileNumberInput').value = report.file_number || '';
  $('prescriberInput').value = report.prescriber || '';
  $('techniqueInput').value = report.content.technique || '';
  $('conclusionInput').value = report.content.conclusion || '';
  resetBulletinPanel();
  renderResults();
  runStatusChecks();

  $('pageTitle').textContent = report.patient_name || 'Sans nom';
  document.querySelector('[data-view="workspace"]').click();
}

async function exportHistory() {
  const reports = await ReportsStore.all();
  const blob = new Blob(
    [JSON.stringify({ exported_at: new Date().toISOString(), reports }, null, 2)],
    { type: 'application/json' }
  );
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `radio-cr-historique-${todayIso()}.json`;
  a.click();
  URL.revokeObjectURL(url);
}

async function importHistory(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;

  try {
    const text = await file.text();
    const data = JSON.parse(text);
    const reports = Array.isArray(data) ? data : data.reports || [];
    let imported = 0;

    for (const report of reports) {
      if (!report.client_uuid) continue;
      const existing = await ReportsStore.get(report.client_uuid);
      if (existing && (existing.updated_at || '') >= (report.updated_at || '')) continue;
      await ReportsStore.put({ ...report, dirty: true });
      imported++;
    }

    await renderHistory();
    alert(`${imported} compte(s) rendu(s) importé(s) depuis l'archive.`);
  } catch (error) {
    alert(`Import impossible : ${error.message}`);
  }
}

/* ------------------------------------------------------------ SYNC */

async function syncNow(silent) {
  if (!state.online) return;

  try {
    const dirtyReports = await ReportsStore.dirty();

    if (dirtyReports.length) {
      const payload = dirtyReports.map((r) => ({
        client_uuid: r.client_uuid,
        hospital_id: r.hospital_id,
        exam_template_id: r.exam_template_id,
        patient_name: r.patient_name,
        patient_age: r.patient_age,
        patient_sex: r.patient_sex,
        file_number: r.file_number,
        prescriber: r.prescriber,
        exam_date: r.exam_date,
        content: r.content,
        status: r.status,
        updated_at: r.updated_at,
      }));

      const { results } = await Api.syncPush(payload);

      for (const outcome of results) {
        const local = dirtyReports.find((r) => r.client_uuid === outcome.client_uuid);
        if (!local) continue;
        if (outcome.outcome === 'created' || outcome.outcome === 'updated') {
          local.id = outcome.id;
          local.dirty = false;
          await ReportsStore.put(local);
        }
      }
    }

    const lastSync = await MetaStore.get('last_sync');
    const pull = await Api.syncPull(lastSync);

    for (const remote of pull.reports) {
      if (remote.deleted) {
        await ReportsStore.remove(remote.client_uuid);
        continue;
      }

      const local = await ReportsStore.get(remote.client_uuid);
      if (local && local.dirty) continue; // des modifications locales non envoyées priment temporairement

      await ReportsStore.put({
        client_uuid: remote.client_uuid,
        id: remote.id,
        hospital_id: remote.hospital_id,
        exam_template_id: remote.exam_template_id,
        patient_name: remote.patient_name,
        patient_age: remote.patient_age,
        patient_sex: remote.patient_sex,
        file_number: remote.file_number,
        prescriber: remote.prescriber,
        exam_date: remote.exam_date,
        content: remote.content,
        status: remote.status,
        updated_at: remote.updated_at,
        dirty: false,
      });
    }

    await MetaStore.set('last_sync', pull.server_time);
    await renderHistory();
    await refreshSyncLabel();
  } catch (error) {
    if (!silent) console.warn('Synchronisation impossible :', error.message);
  }
}

async function refreshSyncLabel() {
  const lastSync = await MetaStore.get('last_sync');
  const label = lastSync ? new Date(lastSync).toLocaleString('fr-FR') : 'Jamais synchronisé';
  $('syncLabel') && ($('syncLabel').textContent = label);
  $('settingsLastSync') && ($('settingsLastSync').textContent = label);
}

/* ------------------------------------------------------------ SETTINGS */

function setupSettings() {
  $('logoutBtn').addEventListener('click', logout);
  $('syncBtn').addEventListener('click', () => syncNow());
}

async function setupSttSettings() {
  const providerSelect = $('sttProviderSelect');
  const localRow = $('sttLocalRow');
  const modelSelect = $('sttLocalModelSelect');

  modelSelect.innerHTML = LOCAL_MODELS.map((m) => `<option value="${m.id}">${escapeHtml(m.label)}</option>`).join('');

  providerSelect.value = await activeProvider();
  localRow.hidden = providerSelect.value !== 'local';
  modelSelect.value = await activeLocalModel();

  providerSelect.addEventListener('change', async () => {
    await setProvider(providerSelect.value);
    localRow.hidden = providerSelect.value !== 'local';
  });

  modelSelect.addEventListener('change', async () => {
    await setLocalModel(modelSelect.value);
  });

  $('sttWarmupBtn').addEventListener('click', async () => {
    $('sttWarmupBtn').disabled = true;
    $('sttLocalStatus').textContent = 'Préparation…';

    try {
      await warmupLocalModel((message) => {
        $('sttLocalStatus').textContent = message;
      });
      $('sttLocalStatus').textContent = 'Modèle prêt et mis en cache pour un usage hors ligne.';
    } catch (error) {
      $('sttLocalStatus').textContent = `Échec : ${error.message}`;
    } finally {
      $('sttWarmupBtn').disabled = false;
    }
  });
}

/* ------------------------------------------------------------ UTIL */

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

boot();
