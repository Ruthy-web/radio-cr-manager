import { ReportsStore, CatalogStore, MetaStore } from './db.js';
import { Api, setUnauthorizedHandler } from './api.js';
import { semanticInsert } from './semantic.js';

const $ = (id) => document.getElementById(id);

const state = {
  user: null,
  hospitals: [],
  report: null,
  online: navigator.onLine,
  recognition: null,
  listening: false,
  audioFile: null,
  heartbeatTimer: null,
  syncTimer: null,
};

/* ------------------------------------------------------------ BOOT */

async function boot() {
  setUnauthorizedHandler(handleUnauthorized);
  window.addEventListener('online', () => setNetwork(true));
  window.addEventListener('offline', () => setNetwork(false));
  setNetwork(navigator.onLine);

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
  setupHistory();
  setupSettings();
  setupSpeechRecognition();

  await loadCatalogWithFallback();
  await renderHistory();
  await refreshSyncLabel();
  newReport();

  startBackgroundTasks();

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
  if (!state.online) {
    $('voiceStatus').textContent = 'Transcription impossible hors ligne.';
    return;
  }

  $('voiceStatus').textContent = 'Transcription en cours…';

  try {
    const result = await Api.transcribe(state.audioFile, state.audioFile.name);
    $('voiceText').value = ($('voiceText').value.trim() + ' ' + result.text).trim();
    $('voiceStatus').textContent = 'Vocal transcrit.';
  } catch (error) {
    $('voiceStatus').textContent = `Transcription impossible : ${error.message}`;
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

/* ------------------------------------------------------------ HISTORY */

function setupHistory() {
  // Rien à initialiser au-delà du rendu, déclenché à la navigation.
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
  renderResults();
  runStatusChecks();

  $('pageTitle').textContent = report.patient_name || 'Sans nom';
  document.querySelector('[data-view="workspace"]').click();
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

/* ------------------------------------------------------------ UTIL */

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

boot();
