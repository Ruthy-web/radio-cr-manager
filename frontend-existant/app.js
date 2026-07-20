/* RadAssist Hybrid - app.js (v5)
   - Hopitaux + examens charges depuis templates.js (templates DOCX reels) + ajouts locaux.
   - En-tete EXACT de chaque hopital (logo embarque) repris a l'impression / Word / PDF.
   - Boutons : Ajouter hopital, Ajouter examen, Importer, Exporter.
   - Lecture bulletin ciblee : Nom, Age, Sexe, Numero (meme sans etiquettes) via IA Vision.
   - Corrections conservees : anti-doublon dictee, modifier au lieu d'ajouter, bouton Imprimer. */

const state = {
  hospitals: [],
  templatesHospitalId: null,
  currentImageData: "",
  recognition: null,
  listening: false,
  voiceBase: "",
  lastAppliedVoice: "",
  deferredPrompt: null,
  db: null,
  assistantHistory: [],
  assistantBusy: false,
  chatAttachments: [],
  chatRecognition: null,
  chatListening: false,
  audioFile: null,
  aiAttachments: []
};

const $ = (id) => document.getElementById(id);
const DEFAULT_ACCESS_CODE = "2606";
const REPORT_FONT = `"Arial Narrow", Arial, sans-serif`;
const REPORT_SIZE = "11pt"; // exigence : Arial Narrow taille 11 sur tous les CR
// Bande verticale gauche du letterhead NKOULOU (image reelle du template).
const NKOULOU_BAND = "data:image/png;base64,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";
const NKOULOU_TEAL = "#45767B";

/* ------------------------------------------------------------------ DONNEES */
function baseHospitals() {
  const data = window.RADASSIST_TEMPLATES || [];
  // copie profonde pour ne pas muter la source
  return JSON.parse(JSON.stringify(data));
}

function getCustomHospitals() {
  return JSON.parse(localStorage.getItem("radassist.customHospitals") || "[]");
}
function saveCustomHospitals(list) {
  localStorage.setItem("radassist.customHospitals", JSON.stringify(list));
}
function getCustomExams() {
  return JSON.parse(localStorage.getItem("radassist.customExams") || "{}");
}
function saveCustomExams(map) {
  localStorage.setItem("radassist.customExams", JSON.stringify(map));
}
function getDeletedExams() {
  return JSON.parse(localStorage.getItem("radassist.deletedExams") || "{}");
}
function saveDeletedExams(map) {
  localStorage.setItem("radassist.deletedExams", JSON.stringify(map));
}
function getDeletedHospitals() {
  return JSON.parse(localStorage.getItem("radassist.deletedHospitals") || "[]");
}
function saveDeletedHospitals(list) {
  localStorage.setItem("radassist.deletedHospitals", JSON.stringify(list));
}
// Surcharges d'examens : permet d'editer meme les templates de base sans les detruire.
function getExamOverrides() {
  return JSON.parse(localStorage.getItem("radassist.examOverrides") || "{}");
}
function saveExamOverrides(map) {
  localStorage.setItem("radassist.examOverrides", JSON.stringify(map));
}
function overrideKey(hospitalId, examId) { return hospitalId + "::" + examId; }
function saveExamOverride(hospitalId, examId, fields) {
  const map = getExamOverrides();
  map[overrideKey(hospitalId, examId)] = fields;
  saveExamOverrides(map);
}

// Fusionne : hopitaux de base (templates.js) + examens ajoutes + hopitaux ajoutes,
// puis retire les hopitaux/examens supprimes par l'utilisateur.
function loadHospitals() {
  const base = baseHospitals();
  const customExams = getCustomExams();
  const delExams = getDeletedExams();
  const delHosp = new Set(getDeletedHospitals());
  base.forEach((h) => {
    h.exams = h.exams || [];
    (customExams[h.id] || []).forEach((ex) => {
      if (!h.exams.some((e) => e.id === ex.id)) h.exams.push(ex);
    });
  });
  const baseIds = new Set(base.map((h) => h.id));
  getCustomHospitals().forEach((h) => { if (!baseIds.has(h.id)) base.push(h); });
  const overrides = getExamOverrides();
  state.hospitals = base
    .filter((h) => !delHosp.has(h.id))
    .map((h) => {
      const removed = new Set(delExams[h.id] || []);
      h.exams = (h.exams || [])
        .filter((e) => !removed.has(e.id))
        .map((e) => {
          const ov = overrides[overrideKey(h.id, e.id)];
          return ov ? Object.assign({}, e, ov) : e;
        });
      return h;
    });
}

// Suppression d'un examen (personnalise -> retire ; de base -> masque).
function deleteExam(hospitalId, examId) {
  const custom = getCustomHospitals();
  const ch = custom.find((h) => h.id === hospitalId);
  if (ch) {
    ch.exams = (ch.exams || []).filter((e) => e.id !== examId);
    saveCustomHospitals(custom);
  }
  const map = getCustomExams();
  if (map[hospitalId]) {
    map[hospitalId] = map[hospitalId].filter((e) => e.id !== examId);
    saveCustomExams(map);
  }
  const del = getDeletedExams();
  del[hospitalId] = del[hospitalId] || [];
  if (!del[hospitalId].includes(examId)) del[hospitalId].push(examId);
  saveDeletedExams(del);
}

// Suppression d'un hopital (ajoute -> retire ; de base -> masque).
function deleteHospital(hospitalId) {
  const custom = getCustomHospitals().filter((h) => h.id !== hospitalId);
  saveCustomHospitals(custom);
  const del = getDeletedHospitals();
  if (!del.includes(hospitalId)) { del.push(hospitalId); saveDeletedHospitals(del); }
}

function template(id, title, requiresSide, technique, results, conclusion, heading) {
  return { id, title, requiresSide, technique, results, conclusion, heading: heading || title };
}

function todayIso() { return new Date().toISOString().slice(0, 10); }

function selectedHospital() {
  return state.hospitals.find((h) => h.id === $("hospitalSelect").value) || state.hospitals[0];
}
function selectedTemplate() {
  const h = selectedHospital();
  if (!h) return null;
  return (h.exams || []).find((e) => e.id === $("examSelect").value) || (h.exams || [])[0];
}

function patientData() {
  const h = selectedHospital();
  return {
    hospital: h ? h.name : "",
    radiologist: h ? (h.radiologist || "Dr E. NDONGO") : "Dr E. NDONGO",
    exam: selectedTemplate()?.title || "",
    side: $("sideSelect").value,
    date: $("dateInput").value,
    lastName: $("lastNameInput").value.trim(),
    firstName: $("firstNameInput").value.trim(),
    age: $("ageInput").value.trim(),
    sex: $("sexInput").value,
    doctor: $("doctorInput").value.trim(),
    record: $("recordInput").value.trim()
  };
}

/* ------------------------------------------------------------------ SECURITE LOCALE */
async function sha256Hex(text) {
  const buf = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(text));
  return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, "0")).join("");
}
// Migration : si un ancien code en clair traine dans localStorage, on le hache puis on l'efface.
async function migrateAccessCode() {
  const plain = localStorage.getItem("radassist.accessCode");
  if (plain) {
    localStorage.setItem("radassist.accessHash", await sha256Hex(plain));
    localStorage.removeItem("radassist.accessCode");
  }
  if (!localStorage.getItem("radassist.accessHash")) {
    localStorage.setItem("radassist.accessHash", await sha256Hex(DEFAULT_ACCESS_CODE));
  }
}
const AUTH_MAX_TRIES = 5;
const AUTH_LOCK_MS = 30000;      // 30 s apres 5 echecs
const IDLE_LOCK_MS = 15 * 60000; // verrouillage auto apres 15 min d'inactivite
let _idleTimer = null;

function setupAuth() {
  const gate = $("authGate");
  if (!gate) return;
  migrateAccessCode();
  if (sessionStorage.getItem("radassist.auth") === "ok") { gate.classList.add("unlocked"); armIdleLock(); }
  $("unlockBtn")?.addEventListener("click", unlockApp);
  $("accessCodeInput")?.addEventListener("keydown", (event) => {
    if (event.key === "Enter") unlockApp();
  });
  // Toute activite repousse le verrouillage automatique.
  ["click", "keydown", "pointerdown"].forEach((ev) =>
    document.addEventListener(ev, () => armIdleLock(), { passive: true }));
}

function armIdleLock() {
  if (sessionStorage.getItem("radassist.auth") !== "ok") return;
  clearTimeout(_idleTimer);
  _idleTimer = setTimeout(lockApp, IDLE_LOCK_MS);
}

function lockApp() {
  sessionStorage.removeItem("radassist.auth");
  $("authGate")?.classList.remove("unlocked");
  const s = $("authStatus"); if (s) s.textContent = "Session verrouillee apres inactivite.";
}

async function unlockApp() {
  const status = $("authStatus");
  const now = Date.now();
  const lockedUntil = Number(sessionStorage.getItem("radassist.lockUntil") || 0);
  if (now < lockedUntil) {
    status.textContent = `Trop d'essais. Reessayez dans ${Math.ceil((lockedUntil - now) / 1000)} s.`;
    return;
  }
  const code = $("accessCodeInput").value.trim();
  const expected = localStorage.getItem("radassist.accessHash");
  if (code && (await sha256Hex(code)) === expected) {
    sessionStorage.setItem("radassist.auth", "ok");
    sessionStorage.removeItem("radassist.tries");
    sessionStorage.removeItem("radassist.lockUntil");
    $("authGate").classList.add("unlocked");
    status.textContent = "";
    $("accessCodeInput").value = "";
    armIdleLock();
    return;
  }
  const tries = Number(sessionStorage.getItem("radassist.tries") || 0) + 1;
  sessionStorage.setItem("radassist.tries", String(tries));
  if (tries >= AUTH_MAX_TRIES) {
    sessionStorage.setItem("radassist.lockUntil", String(now + AUTH_LOCK_MS));
    sessionStorage.setItem("radassist.tries", "0");
    status.textContent = "Trop d'essais : acces bloque 30 secondes.";
  } else {
    status.textContent = `Code incorrect (${tries}/${AUTH_MAX_TRIES}).`;
  }
  $("accessCodeInput").select();
}

function setupAdminCode() {
  const btn = $("changeCodeBtn");
  if (!btn) return;
  btn.addEventListener("click", async () => {
    const v = $("adminCodeInput").value.trim();
    if (v.length < 4) { alert("Choisissez un code d'au moins 4 caracteres."); return; }
    localStorage.setItem("radassist.accessHash", await sha256Hex(v));
    $("adminCodeInput").value = "";
    alert("Code d'acces modifie sur ce poste (stocke sous forme hachee SHA-256).");
  });
}

/* ------------------------------------------------------------------ BASE LOCALE */
function openDatabase() {
  if (!("indexedDB" in window)) return;
  const req = indexedDB.open("radassist-senior", 1);
  req.onupgradeneeded = () => {
    const db = req.result;
    if (!db.objectStoreNames.contains("records")) db.createObjectStore("records", { keyPath: "id" });
  };
  req.onsuccess = () => { state.db = req.result; renderHistory(); };
}

function putRecord(record) {
  return new Promise((resolve) => {
    if (!state.db) { resolve(false); return; }
    const tx = state.db.transaction("records", "readwrite");
    tx.objectStore("records").put(record);
    tx.oncomplete = () => resolve(true);
    tx.onerror = () => resolve(false);
  });
}

function readAllRecords() {
  return new Promise((resolve) => {
    if (!state.db) { resolve(getRecords()); return; }
    const tx = state.db.transaction("records", "readonly");
    const req = tx.objectStore("records").getAll();
    req.onsuccess = () => resolve((req.result || []).sort((a, b) => String(b.createdAt).localeCompare(String(a.createdAt))));
    req.onerror = () => resolve(getRecords());
  });
}

/* ------------------------------------------------------------------ INIT */
function init() {
  setupAuth();
  openDatabase();
  loadHospitals();
  $("dateInput").value = todayIso();
  bindNavigation();
  bindEvents();
  hydrateHospitals();
  renderTemplates();
  renderHistory();
  updateNetworkStatus();
  setupPwa();
  setupSpeech();
  setupPrintButton();
  setupVisionSettings();
  setupTopActions();
  setupExamAddButton();
  setupImportExport();
  setupTemplateImport();
  setupAiDraft();
  setupClaudeSettings();
  setupAssistant();
  setupAdminCode();
  setupHistoryTools();
  registerServiceWorker();
  generateReport();
  runQualityChecks();
}

function bindNavigation() {
  document.querySelectorAll(".nav-item").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll(".nav-item").forEach((i) => i.classList.remove("active"));
      document.querySelectorAll(".view").forEach((v) => v.classList.remove("active"));
      button.classList.add("active");
      $(button.dataset.view).classList.add("active");
      if (button.dataset.view === "templates") { state.templatesHospitalId = null; renderTemplates(); }
    });
  });
}

function bindEvents() {
  $("hospitalSelect").addEventListener("change", () => {
    hydrateExams(); renderTemplates(); generateReport(); runQualityChecks();
  });
  $("examSelect").addEventListener("change", () => { generateReport(); runQualityChecks(); });
  ["sideSelect", "lastNameInput", "firstNameInput", "ageInput", "sexInput", "doctorInput", "recordInput"].forEach((id) => {
    $(id).addEventListener("input", () => { generateReport(); runQualityChecks(); });
  });
  $("imageInput").addEventListener("change", handleImage);
  $("extractPatientBtn").addEventListener("click", extractPatientFromText);
  $("sampleBulletinBtn").addEventListener("click", fillSampleBulletin);
  $("generateBtn").addEventListener("click", generateReport);
  $("voiceBtn").addEventListener("click", toggleVoice);
  $("stopVoiceBtn").addEventListener("click", stopVoice);
  $("applyVoiceBtn").addEventListener("click", applyVoiceText);
  $("audioFileInput")?.addEventListener("change", handleAudioImport);
  $("transcribeBtn")?.addEventListener("click", transcribeImportedAudio);
  $("aiFileInput")?.addEventListener("change", handleAiFiles);
  $("saveBtn").addEventListener("click", saveReport);
  $("resetBtn").addEventListener("click", resetCase);
  $("exportWordBtn").addEventListener("click", exportWord);
  $("exportPdfBtn").addEventListener("click", exportPdf);
  $("reportEditor").addEventListener("input", runQualityChecks);
  window.addEventListener("online", updateNetworkStatus);
  window.addEventListener("offline", updateNetworkStatus);
}

function hydrateHospitals() {
  $("hospitalSelect").innerHTML = state.hospitals
    .map((h) => `<option value="${h.id}">${escapeHtml(h.name)}</option>`).join("");
  hydrateExams();
}
function hydrateExams() {
  const h = selectedHospital();
  $("examSelect").innerHTML = (h?.exams || [])
    .map((e) => `<option value="${e.id}">${escapeHtml(e.title)}</option>`).join("");
}

/* ------------------------------------------------------------------ IMAGE / OCR */
function handleImage(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  const isPdf = file.type === "application/pdf" || /\.pdf$/i.test(file.name || "");
  const reader = new FileReader();
  reader.onload = () => {
    state.currentImageData = reader.result;
    if (isPdf) {
      $("previewImage").hidden = true;
    } else {
      $("previewImage").src = reader.result;
      $("previewImage").hidden = false;
    }
  };
  reader.readAsDataURL(file);
  runAutoOcr(file);
}

// Prompt cible : Nom, Age, Sexe, Numero -- meme sans etiquette explicite.
const VISION_PROMPT = `Tu lis la PHOTO d'un bulletin de demande d'examen radiologique, souvent MANUSCRIT (Cameroun).
Transcris fidelement uniquement ce qui est ecrit a la main par le medecin ; ignore l'entete imprimee du cabinet, les tampons et le fond.
Objectif : extraire surtout NOM du patient, AGE, SEXE, NUMERO du bulletin. Les etiquettes ("Nom", "Age") sont souvent ABSENTES : deduis intelligemment.
Indices :
- Le NOM du patient est le nom propre manuscrit principal, souvent precede de Mr/M./Mme/Mlle et NON suivi de "Dr" (le medecin prescripteur signe en bas).
- Le SEXE se deduit de la civilite : Mr/M. = M ; Mme/Mlle = F. Sinon "".
- L'AGE : s'il y a une date de naissance (ex 21.01.1974), renvoie-la dans dob ; sinon renvoie l'age en clair dans age.
- Le NUMERO du bulletin est souvent en haut, pres d'un N°, d'un tampon ou "OK" (ex 686/DN).
Renvoie UNIQUEMENT un objet JSON strict, sans texte ni Markdown autour :
{"lastName":"","firstName":"","age":"","dob":"","sex":"","record":"","doctor":"","exam":"","side":"","rawText":""}
- N'invente jamais. Champs inconnus = chaine vide. rawText = transcription brute du manuscrit.`;

async function runAutoOcr(file) {
  // Priorite 1 : Claude vision (cle deja integree, lecture manuscrite fiable).
  if (hasClaude()) {
    $("ocrStatus").textContent = "Lecture du bulletin par l'IA Claude en cours...";
    try {
      const parsed = await readWithClaudeVision(file);
      applyVisionResult(parsed);
      return;
    } catch (error) {
      $("ocrStatus").textContent = `IA Claude indisponible (${error.message}). Autre methode...`;
    }
  }
  // Priorite 2 : OpenAI Vision si une cle est configuree.
  const key = (localStorage.getItem("radassist.visionKey") || "").trim();
  if (key) {
    $("ocrStatus").textContent = "Lecture haute precision (OpenAI Vision) en cours...";
    try {
      const parsed = await readWithVision(file, key);
      applyVisionResult(parsed);
      return;
    } catch (error) {
      $("ocrStatus").textContent = `OpenAI Vision indisponible (${error.message}). Bascule sur lecture locale...`;
    }
  }
  // Priorite 3 : lecture locale Tesseract (peu fiable sur manuscrit).
  await runTesseract(file);
}

function extractJsonObject(text) {
  const cleaned = String(text || "").replace(/```json|```/gi, "").trim();

  // Tentative directe.
  try { return JSON.parse(cleaned); } catch (_) {}

  // Tentative sur la sous-chaine entre la premiere { et la derniere }.
  const start = cleaned.indexOf("{");
  const end = cleaned.lastIndexOf("}");
  if (start !== -1 && end > start) {
    try { return JSON.parse(cleaned.slice(start, end + 1)); } catch (_) {}
  }

  // Reponse tronquee (max_tokens atteint en plein milieu du JSON) :
  // on tente une reparation minimale plutot que d'abandonner tout de suite.
  if (start !== -1) {
    let candidate = cleaned.slice(start);
    const openBraces = (candidate.match(/{/g) || []).length;
    const closeBraces = (candidate.match(/}/g) || []).length;
    const openBrackets = (candidate.match(/\[/g) || []).length;
    const closeBrackets = (candidate.match(/]/g) || []).length;
    const quoteCount = (candidate.match(/(?<!\\)"/g) || []).length;
    if (quoteCount % 2 === 1) candidate += '"';
    candidate += "]".repeat(Math.max(0, openBrackets - closeBrackets));
    candidate += "}".repeat(Math.max(0, openBraces - closeBraces));
    try { return JSON.parse(candidate); } catch (_) {}
  }

  const err = new Error("reponse IA illisible (JSON invalide ou tronque)");
  err.rawText = cleaned;
  throw err;
}

/* Garantit que le modele renvoye a toujours la forme attendue par
 * renderGeneratedExam (heading: string, technique: string,
 * results: string[], conclusion: string), quelle que soit la forme
 * exacte renvoyee par Claude (results en string au lieu d'un tableau,
 * champ manquant, etc.) — pour ne plus jamais planter au rendu. */
function normalizeAiExam(parsed) {
  const out = {
    heading: typeof parsed?.heading === "string" ? parsed.heading.trim() : "",
    technique: typeof parsed?.technique === "string" ? parsed.technique.trim() : "",
    conclusion: typeof parsed?.conclusion === "string" ? parsed.conclusion.trim() : "",
    results: []
  };
  const r = parsed?.results;
  if (Array.isArray(r)) {
    out.results = r.map((x) => String(x ?? "").trim()).filter(Boolean);
  } else if (typeof r === "string") {
    out.results = r.split(/\n+/).map((x) => x.replace(/^[-•*]\s*/, "").trim()).filter(Boolean);
  }
  return out;
}

async function readWithClaudeVision(file) {
  const base64 = await fileToBase64(file);
  const { model } = claudeCredentials();
  const isPdf = file.type === "application/pdf" || /\.pdf$/i.test(file.name || "");
  const mediaBlock = isPdf
    ? { type: "document", source: { type: "base64", media_type: "application/pdf", data: base64 } }
    : { type: "image", source: { type: "base64", media_type: (file.type && /^image\//.test(file.type)) ? file.type : "image/jpeg", data: base64 } };
  const data = await anthropicRequest({
    model,
    max_tokens: 900,
    messages: [{ role: "user", content: [mediaBlock, { type: "text", text: VISION_PROMPT }] }]
  });
  const textBlock = (data?.content || []).filter((b) => b.type === "text").map((b) => b.text).join("\n");
  return extractJsonObject(textBlock);
}

function fileToBase64(file) {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(String(r.result).split(",")[1]);
    r.onerror = () => reject(new Error("lecture fichier"));
    r.readAsDataURL(file);
  });
}

async function readWithVision(file, key) {
  const base64 = await fileToBase64(file);
  const model = localStorage.getItem("radassist.visionModel") || "gpt-4o";
  const body = {
    model, temperature: 0, max_tokens: 800,
    messages: [{
      role: "user",
      content: [
        { type: "text", text: VISION_PROMPT },
        { type: "image_url", image_url: { url: `data:${file.type || "image/jpeg"};base64,${base64}` } }
      ]
    }]
  };
  const res = await fetch("https://api.openai.com/v1/chat/completions", {
    method: "POST",
    headers: { "Content-Type": "application/json", Authorization: `Bearer ${key}` },
    body: JSON.stringify(body)
  });
  if (!res.ok) throw new Error("HTTP " + res.status);
  const data = await res.json();
  const raw = data?.choices?.[0]?.message?.content || "";
  return JSON.parse(raw.replace(/```json|```/g, "").trim());
}

function ageFromDob(dob) {
  if (!dob) return "";
  const m = String(dob).match(/(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})/);
  if (!m) return "";
  let [_, d, mo, y] = m;
  y = y.length === 2 ? (Number(y) > 30 ? "19" + y : "20" + y) : y;
  const birth = new Date(Number(y), Number(mo) - 1, Number(d));
  if (isNaN(birth)) return "";
  const now = new Date();
  let age = now.getFullYear() - birth.getFullYear();
  const mm = now.getMonth() - birth.getMonth();
  if (mm < 0 || (mm === 0 && now.getDate() < birth.getDate())) age -= 1;
  return age > 0 && age < 120 ? `${age} ans` : "";
}

function applyVisionResult(p) {
  if (!p || typeof p !== "object") throw new Error("reponse illisible");
  if (p.rawText) $("ocrText").value = p.rawText;
  setIfFound("lastNameInput", cleanName(p.lastName));
  setIfFound("firstNameInput", cleanName(p.firstName));
  const age = p.age && String(p.age).trim() ? String(p.age).trim() : ageFromDob(p.dob);
  setIfFound("ageInput", age);
  if (p.sex && /^[fm]$/i.test(String(p.sex).trim())) $("sexInput").value = String(p.sex).trim().toUpperCase();
  setIfFound("recordInput", p.record);
  setIfFound("doctorInput", cleanDoctor(p.doctor));
  const examId = detectExam(String(p.exam || ""));
  if (examId) $("examSelect").value = examId;
  const side = detectSide(String(p.side || ""));
  if (side) $("sideSelect").value = side;
  const key = ["lastName", "age", "sex", "record"].filter((k) => (k === "age" ? age : p[k])).length;
  $("ocrStatus").textContent = `Lecture IA : ${key}/4 champs cles (nom, age, sexe, numero) detectes. Verifiez.`;
  generateReport();
  runQualityChecks();
}

async function runTesseract(file) {
  if (typeof Tesseract === "undefined") {
    $("ocrStatus").textContent = "Lecture locale indisponible. Ajoutez une cle IA Vision (Parametres) ou saisissez le texte.";
    return;
  }
  $("ocrStatus").textContent = "Lecture locale (Tesseract) en cours...";
  try {
    const processed = await preprocessImage(file).catch(() => file);
    const result = await Tesseract.recognize(processed, "fra", { logger: () => {} });
    const text = (result?.data?.text || "").trim();
    if (text) {
      $("ocrText").value = text;
      extractPatientFromText();
      $("ocrStatus").textContent += " (Manuscrit peu fiable en local : ajoutez une cle IA Vision dans Parametres.)";
    } else {
      $("ocrStatus").textContent = "Aucun texte detecte. Saisissez manuellement.";
    }
  } catch {
    $("ocrStatus").textContent = "Lecture locale impossible. Saisissez le texte manuellement.";
  }
}

async function preprocessImage(file) {
  const url = URL.createObjectURL(file);
  try {
    const img = await new Promise((resolve, reject) => {
      const im = new Image(); im.onload = () => resolve(im); im.onerror = () => reject(new Error("img")); im.src = url;
    });
    const factor = Math.min(2400 / img.width, 3);
    const scale = factor > 1 ? factor : 1;
    const w = Math.round(img.width * scale), h = Math.round(img.height * scale);
    const c = document.createElement("canvas"); c.width = w; c.height = h;
    const ctx = c.getContext("2d"); ctx.drawImage(img, 0, 0, w, h);
    const data = ctx.getImageData(0, 0, w, h); const d = data.data;
    const contrast = 1.4, intercept = 128 * (1 - contrast);
    for (let i = 0; i < d.length; i += 4) {
      const g = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
      let v = contrast * g + intercept; v = v < 0 ? 0 : v > 255 ? 255 : v;
      d[i] = d[i + 1] = d[i + 2] = v;
    }
    ctx.putImageData(data, 0, 0);
    return c.toDataURL("image/png");
  } finally { URL.revokeObjectURL(url); }
}

function setupVisionSettings() {
  const grid = document.querySelector("#settings .settings-grid");
  if (!grid || $("visionKeyInput")) return;
  const block = document.createElement("div");
  block.style.gridColumn = "1 / -1";
  block.innerHTML = `
    <strong>OpenAI Vision (lecture des bulletins manuscrits)</strong>
    <span>Facultatif. Sert uniquement a lire les bulletins manuscrits. Sans cle : lecture locale Tesseract, peu fiable sur l'ecriture a la main.</span>
    <input id="visionKeyInput" type="password" placeholder="sk-..." autocomplete="off" style="margin-top:8px;" />
    <select id="visionModelInput" style="margin-top:8px;">
      <option value="gpt-4o">gpt-4o (precision max)</option>
      <option value="gpt-4o-mini">gpt-4o-mini (rapide)</option>
    </select>

    <hr style="margin:18px 0;opacity:.25;" />

    <strong>Transcription vocale (vocaux WhatsApp)</strong>
    <span>Claude ne transcrit pas l'audio : l'API Anthropic n'accepte aucun bloc audio. Choisissez un moteur de transcription ci-dessous. Claude nettoie ensuite automatiquement le texte obtenu (ponctuation, lexique radiologique, unites).</span>

    <select id="sttProviderInput" style="margin-top:8px;">
      <option value="local">Local — Whisper dans le navigateur (aucune cle, hors ligne, confidentiel)</option>
      <option value="groq">Groq — whisper-large-v3-turbo (rapide, tres bon marche)</option>
      <option value="openai">OpenAI — Whisper / gpt-4o-transcribe</option>
      <option value="proxy">Proxy serveur — la cle reste cote serveur</option>
    </select>

    <div id="sttLocalRow" hidden>
      <select id="sttLocalModelInput" style="margin-top:8px;width:100%;"></select>
      <span style="display:block;font-size:.85em;opacity:.8;margin-top:4px;">Le modele se telecharge une seule fois puis reste en cache. WebGPU utilise si disponible.</span>
      <button type="button" id="sttWarmupBtn" class="icon-btn" style="margin-top:8px;">Telecharger le modele maintenant</button>
    </div>
    <input id="sttGroqKeyInput" type="password" placeholder="gsk_..." autocomplete="off" style="margin-top:8px;" hidden />
    <input id="sttProxyInput" type="text" placeholder="https://mon-worker/stt" autocomplete="off" style="margin-top:8px;" hidden />
    <span id="sttHint" style="display:block;margin-top:8px;font-size:.85em;"></span>`;
  grid.appendChild(block);

  // --- Vision (inchange)
  const k = block.querySelector("#visionKeyInput"), m = block.querySelector("#visionModelInput");
  k.value = localStorage.getItem("radassist.visionKey") || "";
  m.value = localStorage.getItem("radassist.visionModel") || "gpt-4o";
  k.addEventListener("change", () => localStorage.setItem("radassist.visionKey", k.value.trim()));
  m.addEventListener("change", () => localStorage.setItem("radassist.visionModel", m.value));

  // --- Transcription vocale
  const prov = block.querySelector("#sttProviderInput");
  const localRow = block.querySelector("#sttLocalRow");
  const localModel = block.querySelector("#sttLocalModelInput");
  const groqKey = block.querySelector("#sttGroqKeyInput");
  const proxy = block.querySelector("#sttProxyInput");
  const hint = block.querySelector("#sttHint");
  const warmBtn = block.querySelector("#sttWarmupBtn");

  import("./stt.js?v=17").then(({ LOCAL_MODELS }) => {
    localModel.innerHTML = LOCAL_MODELS.map((x) => `<option value="${x.id}">${x.label}</option>`).join("");
    localModel.value = localStorage.getItem("radassist.sttLocalModel") || "onnx-community/whisper-small";
  }).catch(() => { hint.textContent = "stt.js introuvable : servez l'application via un serveur local."; });

  prov.value = localStorage.getItem("radassist.sttProvider") || "local";
  groqKey.value = localStorage.getItem("radassist.groqKey") || "";
  proxy.value = localStorage.getItem("radassist.sttProxy") || "";

  const refresh = () => {
    const p = prov.value;
    localRow.hidden = p !== "local";
    groqKey.hidden = p !== "groq";
    proxy.hidden = p !== "proxy";
    hint.textContent =
      p === "local" ? "L'audio ne quitte jamais cet appareil — recommande pour le secret medical." :
      p === "groq" ? "Le vocal est envoye a Groq. A eviter si le vocal contient des donnees nominatives de patient." :
      p === "openai" ? "Utilise la cle OpenAI ci-dessus. Le vocal est envoye a OpenAI." :
      "Le vocal transite par votre serveur, qui detient la cle.";
    hint.style.color = p === "local" ? "#1a7f37" : "#9a6700";
  };
  refresh();

  prov.addEventListener("change", () => { localStorage.setItem("radassist.sttProvider", prov.value); refresh(); });
  localModel.addEventListener("change", () => localStorage.setItem("radassist.sttLocalModel", localModel.value));
  groqKey.addEventListener("change", () => localStorage.setItem("radassist.groqKey", groqKey.value.trim()));
  proxy.addEventListener("change", () => localStorage.setItem("radassist.sttProxy", proxy.value.trim()));

  warmBtn.addEventListener("click", async () => {
    warmBtn.disabled = true;
    try {
      const { warmupLocalModel } = await import("./stt.js?v=17");
      warmupLocalModel((msg) => { hint.textContent = msg; hint.style.color = "#1a7f37"; });
    } catch (e) {
      hint.textContent = "Echec : " + e.message; hint.style.color = "#b42318";
    } finally {
      setTimeout(() => (warmBtn.disabled = false), 2000);
    }
  });
}

function fillSampleBulletin() {
  $("ocrText").value = "Mr FEMONA Marius 21.01.1974 Rx lombosacree N 686/DN Dr Akoumou Marie Lucie";
  extractPatientFromText();
}

/* ------------------------------------------------------------------ OCR texte (fallback) */
function extractPatientFromText() {
  const raw = $("ocrText").value.trim();
  if (!raw) { $("ocrStatus").textContent = "Ajoutez d'abord le texte lu sur le bulletin."; return; }
  const text = normalizeText(raw);
  const dob = pickPattern(text, /(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/);
  let lastName = pickValue(text, ["noms", "nom"], ["prenoms", "prenom", "age", "sexe", "examen", "medecin", "docteur", "numero", "n dossier"]);
  let firstName = pickValue(text, ["prenoms", "prenom"], ["age", "sexe", "examen", "medecin", "docteur", "numero", "n dossier"]);
  // Si aucune etiquette Nom/Prenom : detecter via la civilite (Mr/Mme/Mlle...) le nom propre qui suit.
  if (!lastName) {
    const civ = raw.match(/\b(?:mr|m\.?|mme|mlle|monsieur|madame|mademoiselle)\s+([A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'’-]+(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'’-]+){0,2})/i);
    if (civ) {
      const parts = civ[1].split(/\s+/).filter(Boolean);
      lastName = parts[0] || "";
      if (!firstName) firstName = parts.slice(1).join(" ");
    }
  }
  const detected = {
    lastName,
    firstName,
    age: pickPattern(text, /(?:age|âge)\s*[:\-]?\s*([0-9]{1,3}\s*(?:ans|an|mois)?)/i) || ageFromDob(dob),
    sex: pickPattern(text, /(?:sexe)\s*[:\-]?\s*([fm])/i) || (/\bmr\b|\bm\.\b|monsieur/i.test(text) ? "M" : (/\bmme\b|\bmlle\b|madame|mademoiselle/i.test(text) ? "F" : "")),
    doctor: pickValue(text, ["medecin", "docteur", "dr"], ["numero", "n dossier", "examen", "indication"]),
    record: pickPattern(text, /(?:\bnumero\b|\bn\s*dossier\b|\bn°\b|\bno?\b)\s*[:\-]?\s*([a-z0-9\-\/]+)/i),
    exam: detectExam(text),
    side: detectSide(text)
  };
  setIfFound("lastNameInput", cleanName(detected.lastName));
  setIfFound("firstNameInput", cleanName(detected.firstName));
  setIfFound("ageInput", detected.age);
  if (detected.sex) $("sexInput").value = detected.sex.toUpperCase();
  setIfFound("doctorInput", cleanDoctor(detected.doctor));
  setIfFound("recordInput", detected.record);
  if (detected.exam) $("examSelect").value = detected.exam;
  if (detected.side) $("sideSelect").value = detected.side;
  const filled = Object.values(detected).filter(Boolean).length;
  $("ocrStatus").textContent = filled ? `${filled} information(s) detectee(s). Verifiez.` : "Aucun champ reconnu. Saisissez manuellement.";
  generateReport(); runQualityChecks();
}

function normalizeText(v) { return v.replace(/[’']/g, " ").replace(/\s+/g, " ").trim(); }
function pickValue(text, labels, stops) {
  const l = labels.map(escapeRegex).join("|"), s = stops.map(escapeRegex).join("|");
  const m = text.match(new RegExp(`(?:${l})\\s*[:\\-]?\\s*(.+?)(?=\\s+(?:${s})\\b|$)`, "i"));
  return m?.[1]?.trim() || "";
}
function pickPattern(text, re) { return text.match(re)?.[1]?.trim() || ""; }

function detectExam(text) {
  const v = text.toLowerCase();
  const exams = selectedHospital()?.exams || [];
  // 1) correspondance directe avec un titre d'exame de l'hopital courant
  let hit = exams.find((e) => v.includes(e.title.toLowerCase()));
  if (hit) return hit.id;
  // 2) mots-cles usuels
  const kw = [
    ["thorax", ["thorax", "pulmonaire", "thoracique"]], ["lombaire", ["lombaire", "lombo", "lombosacr"]],
    ["cervical", ["cervical"]], ["dorsal", ["dorsal"]], ["bassin", ["bassin", "pelvien"]],
    ["hanche", ["hanche", "coxo", "coxarthr"]], ["genou", ["genou"]], ["cheville", ["cheville"]],
    ["pied", ["pied"]], ["epaule", ["epaule", "épaule"]], ["coude", ["coude"]], ["poignet", ["poignet"]],
    ["main", ["main"]], ["crane", ["crane", "crâne"]], ["sinus", ["sinus"]], ["abdomen", ["abdomen", "asp"]]
  ];
  const found = kw.find(([, words]) => words.some((w) => v.includes(w)));
  if (found) { hit = exams.find((e) => e.title.toLowerCase().includes(found[0])); if (hit) return hit.id; }
  return "";
}
function detectSide(text) {
  const v = text.toLowerCase();
  if (v.includes("bilateral")) return "Bilateral";
  if (v.includes("gauche")) return "Gauche";
  if (v.includes("droit")) return "Droit";
  return "";
}
function setIfFound(id, v) { if (v) $(id).value = v; }
function cleanName(v) {
  return String(v || "").replace(/\b(mr|m|mme|mlle|patient|patiente|monsieur|madame)\b/gi, "")
    .replace(/[^a-zA-ZÀ-ÿ\s-]/g, "").trim().toUpperCase();
}
function cleanDoctor(v) {
  const c = String(v || "").replace(/\b(examen|radio|radiographie)\b.*$/i, "").trim();
  if (!c) return "";
  return /^dr\b/i.test(c) ? c : `Dr ${c}`;
}
function escapeRegex(v) { return v.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"); }

/* ------------------------------------------------------------------ EN-TETE EXACT */
function renderHeader(hospital) {
  const head = hospital.header;
  if (!head) return `<div class="cr-header" style="font-family:${REPORT_FONT};text-align:center;font-weight:800;border-bottom:2px solid #1F3864;margin-bottom:14px;padding-bottom:8px;">${escapeHtml(hospital.name)}</div>`;
  const color = head.color || "#1F3864";
  const font = REPORT_FONT;
  if (hospital.id === "nkoulou") {
    const logo = head.logo ? `<img src="${head.logo}" alt="logo" style="width:66px;height:auto;" />` : "";
    return `
      <table style="width:100%;border-collapse:collapse;margin-bottom:8px;"><tr>
        <td style="width:80px;vertical-align:top;padding:0;">${logo}</td>
        <td style="vertical-align:middle;padding:4px 0 0;">
          <div style="color:#45767B;font-family:Arial,Helvetica,sans-serif;font-weight:800;font-size:18px;line-height:1.15;">CABINET POLYCLINIQUE MÉDICO-CHIRURGICALE DE LA CITÉ</div>
          <div style="color:#6b7f82;font-family:Arial,Helvetica,sans-serif;font-size:13px;margin-top:2px;">(Dr Anne-Marie NKOULOU)</div>
        </td>
      </tr></table>
      <table style="width:100%;border-collapse:collapse;"><tr>
        <td style="width:125px;vertical-align:top;padding:0 14px 0 0;"><img src="${NKOULOU_BAND}" alt="Coordonnees et specialites de la clinique" style="width:115px;height:auto;display:block;" /></td>
        <td style="vertical-align:top;padding:0;">`;
  }
  if (head.layout === "banner" && head.logo) {
    return `<div class="cr-header" style="font-family:${font};margin-bottom:14px;border-bottom:2px solid ${color};padding-bottom:8px;">
      <img src="${head.logo}" alt="en-tete" style="width:100%;max-height:120px;object-fit:contain;display:block;" />
    </div>`;
  }
  const titleLines = ((head.lines || []).length ? head.lines : [hospital.name]).map((l) => `<div>${escapeHtml(l)}</div>`).join("");
  const sub = head.sub ? `<div style="font-size:11px;color:#475569;font-weight:400;margin-top:2px;">${escapeHtml(head.sub)}</div>` : "";
  const logo = head.logo ? `<td style="width:110px;vertical-align:middle;padding-right:10px;"><img src="${head.logo}" alt="logo" style="max-width:100px;max-height:80px;object-fit:contain;" /></td>` : "";
  return `<div class="cr-header" style="border-bottom:2px solid ${color};margin-bottom:14px;padding-bottom:8px;">
    <table style="width:100%;border-collapse:collapse;"><tr>
      ${logo}
      <td style="vertical-align:middle;text-align:center;font-family:${font};color:${color};font-weight:800;font-size:15px;line-height:1.25;">
        ${titleLines}${sub}
      </td>
    </tr></table>
  </div>`;
}

function generateReport() {
  const data = patientData();
  const model = selectedTemplate();
  const hospital = selectedHospital();
  if (!model || !hospital) { $("reportEditor").innerHTML = ""; return; }
  const heading = model.heading || `Compte Rendu de ${model.title}`;
  const sideLine = model.requiresSide && data.side ? ` — Côté : ${data.side}` : "";
  const technique = model.technique ? `<h3>Technique</h3><p>${escapeHtml(model.technique)}</p>` : "";
  const closePaper = hospital.id === "nkoulou" ? "</td></tr></table>" : "";
  $("reportEditor").innerHTML = `
    <div class="cr-paper" style="font-family:${REPORT_FONT};font-size:${REPORT_SIZE};">
    ${renderHeader(hospital)}
    <h2>${escapeHtml(heading)}${escapeHtml(sideLine)}</h2>
    <h3>Identification</h3>
    <p><strong>Nom :</strong> ${escapeHtml(data.lastName)} &nbsp;&nbsp; <strong>Prénom :</strong> ${escapeHtml(data.firstName)} &nbsp;&nbsp; <strong>Âge :</strong> ${escapeHtml(data.age)} &nbsp;&nbsp; <strong>Sexe :</strong> ${escapeHtml(data.sex)}</p>
    <p><strong>Date :</strong> ${escapeHtml(data.date)} &nbsp;&nbsp; <strong>Médecin :</strong> ${escapeHtml(data.doctor)} &nbsp;&nbsp; <strong>N° :</strong> ${escapeHtml(data.record)}</p>
    ${technique}
    <h3>Résultats</h3>
    <ul>${(model.results || []).map((l) => `<li>${escapeHtml(l)}</li>`).join("")}</ul>
    <h3>Conclusion</h3>
    <p>${escapeHtml(model.conclusion || "")}</p>
    <p style="text-align:right; margin-top:42px;"><strong>${escapeHtml(data.radiologist)}</strong><br/>Radiologue</p>
    ${closePaper}
    </div>
  `;
  runQualityChecks();
}

/* ------------------------------------------------------------------ DICTEE */
function setupSpeech() {
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) {
    $("voiceBtn").disabled = true; $("stopVoiceBtn").disabled = true;
    $("voiceBtn").title = "Dictee non disponible dans ce navigateur";
    setVoiceStatus("Dictee non disponible ici. Utilisez Chrome/Edge ou le clavier vocal.", "blocked");
    return;
  }
  state.recognition = new SR();
  state.recognition.lang = "fr-FR";
  state.recognition.continuous = false;
  state.recognition.interimResults = true;
  setVoiceStatus("Pret. Cliquez sur Dicter puis autorisez le micro.", "ready");
  state.recognition.onstart = () => {
    state.listening = true;
    const ex = $("voiceText").value.trim();
    state.voiceBase = ex ? ex + " " : "";
    $("voiceBtn").classList.add("recording");
    setVoiceStatus("Ecoute en cours...", "recording");
  };
  state.recognition.onresult = (event) => {
    let finalText = "", interim = "";
    for (let i = 0; i < event.results.length; i += 1) {
      const tr = event.results[i][0].transcript;
      if (event.results[i].isFinal) finalText += tr + " "; else interim += tr;
    }
    $("voiceText").value = `${state.voiceBase}${finalText}${interim}`.replace(/\s+/g, " ").trim();
  };
  state.recognition.onend = () => {
    state.listening = false; $("voiceBtn").classList.remove("recording");
    setVoiceStatus("Dictee arretee. Relisez puis inserez.", "ready");
    if ($("voiceText").value.trim()) applyVoiceText(true);
  };
  state.recognition.onerror = (event) => {
    state.listening = false; $("voiceBtn").classList.remove("recording");
    const msg = event.error === "not-allowed" ? "Micro bloque. Autorisez le micro dans le navigateur."
      : `Probleme de dictee: ${event.error}. Vous pouvez taper le texte.`;
    setVoiceStatus(msg, "blocked");
  };
}
function toggleVoice() {
  if (!state.recognition) return;
  if (state.listening) stopVoice();
  else { try { state.recognition.start(); } catch { setVoiceStatus("Dictee deja active ou bloquee.", "blocked"); } }
}
function stopVoice() { if (state.recognition && state.listening) state.recognition.stop(); }
function setVoiceStatus(m, s) { $("voiceStatus").textContent = m; $("voiceStatus").className = s || ""; }

function applyVoiceText(auto = false) {
  const text = $("voiceText").value.trim();
  if (!text) return;
  const cleaned = cleanupDictation(text);
  if (auto && cleaned === state.lastAppliedVoice) return;
  if (!$("reportEditor").innerHTML.trim()) generateReport();
  // 1) Remplacement semantique INSTANTANE (hors ligne, < 5 ms) : la bonne ligne du
  //    template est modifiee immediatement, la conclusion suit si une anomalie est dictee.
  const r = semanticInsert(cleaned);
  setVoiceStatus(`Dictee integree : ${r.replaced} ligne(s) modifiee(s), ${r.added} ajoutee(s).` + (hasClaude() && !auto ? " Raffinage IA..." : ""), "ready");
  state.lastAppliedVoice = cleaned;
  // 2) Raffinage par Claude en arriere-plan (reformulation senior + conclusion) si cle disponible.
  if (hasClaude() && !auto) insertDictationWithClaude(cleaned);
}

// Recupere la liste des resultats et la conclusion du compte rendu affiche.
function currentReportSections() {
  const editor = $("reportEditor");
  const headings = [...editor.querySelectorAll("h3")];
  const resHeading = headings.find((h) => /r[ée]sultats|descriptif|constatations/i.test(h.textContent));
  const conclHeading = headings.find((h) => /conclusion/i.test(h.textContent));
  let ul = resHeading && resHeading.nextElementSibling?.tagName === "UL" ? resHeading.nextElementSibling : editor.querySelector("ul");
  const results = ul ? [...ul.querySelectorAll("li")].map((li) => li.textContent.trim()) : [];
  let conclP = conclHeading && conclHeading.nextElementSibling?.tagName === "P" ? conclHeading.nextElementSibling : null;
  const conclusion = conclP ? conclP.textContent.trim() : "";
  return { ul, results, conclP, conclusion };
}

async function insertDictationWithClaude(dictation) {
  setVoiceStatus("Integration intelligente de la dictee...", "recording");
  try {
    const { ul, results, conclP, conclusion } = currentReportSections();
    const system =
      "Tu es un radiologue senior. On te donne les RESULTATS actuels d'un compte rendu (liste), la CONCLUSION actuelle, " +
      "et la DICTEE d'un radiologue decrivant des constatations. Ta tache : integrer les constatations dictees dans les resultats. " +
      "REGLE CLE : si une structure anatomique citee dans la dictee (foie, rate, reins, vesicule, pancreas, aorte, uterus, prostate, os, tendon, etc.) " +
      "figure deja dans une ligne des resultats, REMPLACE cette ligne par la version dictee reformulee proprement (terminologie medicale exacte, style CR). " +
      "Sinon, AJOUTE une nouvelle ligne au bon endroit. Ne touche pas aux lignes non concernees. Corrige la conclusion si la dictee l'impose (ex : passage de 'normal' a une anomalie). " +
      "N'invente aucune donnee non dictee. Reponds UNIQUEMENT en JSON strict : {\"results\":[\"...\"],\"conclusion\":\"...\"}.";
    const userMsg =
      `RESULTATS actuels : ${JSON.stringify(results)}\n` +
      `CONCLUSION actuelle : ${JSON.stringify(conclusion)}\n` +
      `DICTEE : ${JSON.stringify(dictation)}`;
    const raw = await claudeText(system, userMsg, 1500);
    const parsed = extractJsonObject(raw);
    const editor = $("reportEditor");
    // Remplacer la liste des resultats.
    if (Array.isArray(parsed.results) && parsed.results.length) {
      const items = parsed.results.map((r) => `<li>${escapeHtml(r)}</li>`).join("");
      if (ul) ul.innerHTML = items;
      else {
        const headings = [...editor.querySelectorAll("h3")];
        const resHeading = headings.find((h) => /r[ée]sultats|descriptif/i.test(h.textContent));
        if (resHeading) resHeading.insertAdjacentHTML("afterend", `<ul>${items}</ul>`);
        else editor.insertAdjacentHTML("beforeend", `<h3>Résultats</h3><ul>${items}</ul>`);
      }
    }
    // Mettre a jour la conclusion.
    if (parsed.conclusion) {
      if (conclP) conclP.textContent = parsed.conclusion;
      else {
        const headings = [...editor.querySelectorAll("h3")];
        const conclHeading = headings.find((h) => /conclusion/i.test(h.textContent));
        if (conclHeading) conclHeading.insertAdjacentHTML("afterend", `<p>${escapeHtml(parsed.conclusion)}</p>`);
        else editor.insertAdjacentHTML("beforeend", `<h3>Conclusion</h3><p>${escapeHtml(parsed.conclusion)}</p>`);
      }
    }
    state.lastAppliedVoice = dictation;
    setVoiceStatus("Dictee integree intelligemment. A valider.", "ready");
    runQualityChecks();
  } catch (error) {
    setVoiceStatus("Integration IA impossible (" + error.message + "). Insertion simple.", "blocked");
    insertIntoSection("Resultats", dictation);
    runQualityChecks();
  }
}

/* -------------------------------------------------- Import + transcription d'un vocal (WhatsApp) */
function handleAudioImport(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  state.audioFile = file;
  $("audioName").textContent = file.name;
  setVoiceStatus("Vocal pret. Cliquez sur \"Generer (transcrire)\".", "ready");
  event.target.value = "";
}

async function transcribeImportedAudio() {
  if (!state.audioFile) { setVoiceStatus("Importez d'abord un vocal.", "blocked"); return; }

  let stt;
  try {
    stt = await import("./stt.js?v=17");
  } catch (e) {
    setVoiceStatus("Module de transcription introuvable (stt.js). Servez l'app via un serveur local, pas en file://.", "blocked");
    return;
  }

  if (!stt.sttReady()) { setVoiceStatus(stt.sttUnavailableReason(), "blocked"); return; }

  const btn = $("transcribeBtn");
  if (btn) btn.disabled = true;
  try {
    const { text, provider } = await stt.transcribeAudio(
      state.audioFile,
      (msg) => setVoiceStatus(msg, "recording")
    );
    if (!text) throw new Error("aucune parole detectee dans le vocal");

    // Claude ne transcrit pas l'audio, mais il normalise remarquablement bien la sortie de Whisper.
    let clean = text;
    if (hasClaude()) {
      setVoiceStatus("Normalisation medicale par Claude...", "recording");
      try { clean = await polishDictation(text); }
      catch (e) { console.warn("Normalisation Claude ignoree :", e.message); }
    }

    const prev = $("voiceText").value.trim();
    $("voiceText").value = prev ? prev + " " + clean : clean;

    // Un seul clic : integration semantique instantanee, puis raffinage Claude.
    if (!$("reportEditor").innerHTML.trim()) generateReport();
    const r = semanticInsert(cleanupDictation(clean));
    setVoiceStatus(`Vocal transcrit (${provider}) : ${r.replaced} ligne(s) modifiee(s), ${r.added} ajoutee(s).` + (hasClaude() ? " Raffinage IA..." : ""), "ready");
    if (hasClaude()) await insertDictationWithClaude(cleanupDictation(clean));
  } catch (error) {
    setVoiceStatus("Transcription impossible : " + error.message, "blocked");
  } finally {
    if (btn) btn.disabled = false;
  }
}

const DICTATION_POLISH_PROMPT =
  "Tu es correcteur de dictee pour un radiologue francophone (Cameroun). " +
  "On te donne la sortie BRUTE d'un moteur de reconnaissance vocale. Ta tache : " +
  "(1) restaurer la ponctuation et les majuscules ; " +
  "(2) corriger les termes radiologiques et anatomiques mal transcrits " +
  "(ex. hypoechogene, cholédoque, parenchyme, BI-RADS, sus-hepatique, hydronephrose) ; " +
  "(3) restituer nombres, unites et mesures au format standard (12 mm, 4,5 cm, 25 UI/L) ; " +
  "(4) supprimer les hesitations (euh, repetitions, faux departs). " +
  "INTERDIT : ajouter, retirer ou reformuler une information clinique. Aucune interpretation, aucun diagnostic. " +
  "En cas de mot indechiffrable, garde-le tel quel entre crochets. " +
  "Reponds UNIQUEMENT par le texte corrige, sans preambule ni commentaire.";

async function polishDictation(raw) {
  const out = await claudeText(DICTATION_POLISH_PROMPT, raw, 1500);
  const t = (out || "").trim();
  // Garde-fou : si le modele derape (bavardage), on conserve la transcription brute.
  if (!t || t.length > raw.length * 2.2) return raw;
  return t;
}

/* -------------------------------------------------- Fichiers joints au compte rendu libre */
async function handleAiFiles(event) {
  const files = Array.from(event.target.files || []);
  for (const file of files) {
    const name = file.name || "fichier";
    const lower = name.toLowerCase();
    try {
      if (file.type === "application/pdf" || lower.endsWith(".pdf")) {
        state.aiAttachments.push({ name, kind: "pdf", data: await fileToBase64(file) });
      } else if (file.type && /^image\//.test(file.type)) {
        state.aiAttachments.push({ name, kind: "image", mediaType: file.type, data: await fileToBase64(file) });
      } else if (lower.endsWith(".docx")) {
        const mammoth = await loadMammoth();
        const out = await mammoth.extractRawText({ arrayBuffer: await fileToArrayBuffer(file) });
        state.aiAttachments.push({ name, kind: "text", text: out.value || "" });
      } else if (/\.(txt|csv|md|json)$/i.test(lower) || (file.type && /^text\//.test(file.type))) {
        state.aiAttachments.push({ name, kind: "text", text: await fileToText(file) });
      } else {
        $("aiStatus").textContent = `Format non pris en charge : ${name}`;
      }
    } catch (err) { $("aiStatus").textContent = `Fichier "${name}" non traite : ${err.message}`; }
  }
  event.target.value = "";
  $("aiFiles").textContent = state.aiAttachments.length ? state.aiAttachments.map((a) => a.name).join(", ") : "";
}


function cleanupDictation(t) {
  return t.replace(/^conclusion[:,]?\s*/i, "").replace(/^resultats?[:,]?\s*/i, "").trim();
}


/* ------------------------------------------------------------------ INSERTION SEMANTIQUE INSTANTANEE
   Remplace la ligne du template qui parle de la MEME structure anatomique / du MEME parametre,
   au lieu d'ajouter en doublon. Fonctionne hors ligne, en < 5 ms ; Claude affine ensuite si une
   cle est configuree. Exemple : "battement cardiaque = 377 bpm" -> remplace la ligne de l'index
   cardio-thoracique / frequence cardiaque ; "veine saphene gauche obstruee" -> remplace la ligne
   qui mentionne la saphene gauche. */
const TERM_SYNONYMS = [
  ["battement cardiaque", "frequence cardiaque", "rythme cardiaque", "bpm", "activite cardiaque", "bdc", "silhouette cardiaque", "index cardio thoracique"],
  ["saphene", "grande veine saphene", "petite veine saphene", "crosse saphene"],
  ["vesicule", "vesicule biliaire", "vb"],
  ["voie biliaire", "choledoque", "vbp", "voies biliaires"],
  ["rein", "reins", "renal", "renale", "pyelocalicielle", "pyelocaliciel"],
  ["foie", "hepatique", "hepatomegalie", "fleche hepatique", "parenchyme hepatique"],
  ["rate", "splenique", "splenomegalie"],
  ["pancreas", "pancreatique", "wirsung"],
  ["uterus", "uterin", "uterine", "myometre"],
  ["endometre", "endometriale", "ligne endometriale"],
  ["ovaire", "ovarien", "ovarienne", "annexe", "annexiel"],
  ["prostate", "prostatique"],
  ["vessie", "vesical", "vesicale"],
  ["thyroide", "thyroidien", "thyroidienne", "lobe thyroidien"],
  ["plevre", "pleural", "epanchement pleural", "culs de sac costodiaphragmatiques", "cul de sac"],
  ["poumon", "pulmonaire", "transparence pulmonaire", "parenchyme pulmonaire", "opacite", "alveolaire", "condensation", "foyer", "lobe", "hyperclarte", "pneumothorax", "bulle", "nodule pulmonaire"],
  ["mediastin", "mediastinal"],
  ["coupole", "diaphragme", "diaphragmatique", "coupoles diaphragmatiques"],
  ["aorte", "aortique"],
  ["veine cave", "vci"],
  ["carotide", "carotidien"],
  ["femorale", "femoral", "veine femorale", "artere femorale"],
  ["poplitee", "poplite"],
  ["tibial", "tibiale", "tibia"],
  ["fibula", "perone", "peroneal"],
  ["femur", "femoral"],
  ["humerus", "humeral", "humerale"],
  ["interligne", "interlignes", "pincement articulaire"],
  ["epanchement", "liquide libre", "lame liquidienne", "douglas"],
  ["ganglion", "adenopathie", "adenomegalie", "ganglionnaire"],
  ["placenta", "placentaire"],
  ["liquide amniotique", "grande citerne", "ila"],
  ["col uterin", "col", "cervical"],
  ["tendon", "tendineux", "coiffe des rotateurs", "supra epineux", "sus epineux"],
  ["sinus", "sinusien", "maxillaire", "frontal", "ethmoidal", "sphenoidal"],
  ["testicule", "testiculaire", "scrotal", "epididyme"],
  ["sein", "mammaire", "glande mammaire"],
  ["fracture", "trait de fracture", "lyse", "corticale"],
  ["thrombose", "thrombus", "obstrue", "obstruee", "occlusion", "incompressible", "compressibilite"]
];
const PATHOLOGY_WORDS = /\b(obstru|thrombos|thrombus|fractur|lesion|nodul|masse|tumeur|kyste|dilat|epanchement|stenos|anevrysm|adenopath|metastas|hydronephros|lithias|calcul|augment|hypertroph|epaissi|infiltrat|opacit|hyperclart|luxation|tassement|hernie|pincement|oedem|abces|collection|incompressib|reflux|insuffisan|anormal|suspect|hypoecho|hyperecho|heterogen)\w*/i;

function normalizeSem(v) {
  return String(v || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9,;.=]/g, " ").replace(/\s+/g, " ").trim();
}
function expandWithSynonyms(tokens) {
  const set = new Set(tokens);
  const joined = " " + tokens.join(" ") + " ";
  for (const group of TERM_SYNONYMS) {
    if (group.some((g) => joined.includes(" " + normalizeSem(g) + " ") || tokens.some((t) => normalizeSem(g).split(" ").includes(t)))) {
      group.forEach((g) => normalizeSem(g).split(" ").forEach((w) => { if (w.length > 2) set.add(w); }));
    }
  }
  return set;
}
function statementTokens(s) {
  return normalizeSem(s).replace(/[,;.=]/g, " ").split(" ").filter((w) => w.length > 2 && !MATCH_STOP.has(w));
}
// Score de correspondance entre un enonce dicte et une ligne du template.
function matchScore(stmtTokens, lineText) {
  const lineTokens = new Set(statementTokens(lineText));
  if (!lineTokens.size) return 0;
  const expanded = expandWithSynonyms([...stmtTokens]);
  let hits = 0;
  for (const t of lineTokens) if (expanded.has(t)) hits++;
  // Bonus lateralite concordante, malus lateralite opposee (gauche vs droite).
  const sSide = /gauche/.test([...expanded].join(" ")) ? "g" : (/droit/.test([...expanded].join(" ")) ? "d" : "");
  const lSide = /gauche/.test(normalizeSem(lineText)) ? "g" : (/droit/.test(normalizeSem(lineText)) ? "d" : "");
  let bonus = 0;
  if (sSide && lSide) bonus += (sSide === lSide) ? 1.5 : -2;
  return hits + bonus;
}
function splitStatements(dictation) {
  return dictation.split(/(?<=[.;])\s+|\n+|\bensuite\b|\bpar ailleurs\b|\bde plus\b/i)
    .map((s) => s.replace(/^[,;.\s]+|[,;\s]+$/g, "").trim())
    .filter((s) => s.length > 3);
}
function polishStatement(s) {
  let t = s.trim().replace(/\s+/g, " ");
  t = t.replace(/\s*=\s*/g, " : ").replace(/(\d)\s*bpm/gi, "$1 bpm").replace(/(\d)\s*mm/gi, "$1 mm").replace(/(\d)\s*cm/gi, "$1 cm");
  t = t.charAt(0).toUpperCase() + t.slice(1);
  if (!/[.]$/.test(t)) t += ".";
  return t;
}
/** Insere une dictee dans le CR affiche : remplacement semantique ligne a ligne, puis conclusion. */
function semanticInsert(dictation) {
  const editor = $("reportEditor");
  if (!editor.innerHTML.trim()) generateReport();
  const { ul, conclP, conclusion } = currentReportSections();
  const statements = splitStatements(dictation);
  const anomalies = [];
  let replaced = 0, added = 0;
  for (const stmt of statements) {
    // La phrase "conclusion : ..." va directement dans la conclusion.
    const mC = stmt.match(/^conclusions?\s*:?\s*(.+)$/i);
    if (mC && conclP) { conclP.textContent = polishStatement(mC[1]); continue; }
    const tokens = statementTokens(stmt);
    if (!tokens.length) continue;
    const items = ul ? [...ul.querySelectorAll("li")] : [];
    let best = null, bestScore = 0;
    for (const li of items) {
      const sc = matchScore(tokens, li.textContent);
      if (sc > bestScore) { bestScore = sc; best = li; }
    }
    const isPatho = PATHOLOGY_WORDS.test(stmt);
    if (best && bestScore >= 2) {
      // Cas "parametre : valeur" -> on ne remplace que la valeur numerique dans la ligne.
      const mVal = stmt.match(/^(.{3,60}?)[:=]\s*([\d.,]+\s*(bpm|mm|cm|ml|g\/l|ui\/l|%|sa)?)\s*$/i);
      if (mVal && /\d/.test(best.textContent)) {
        best.textContent = best.textContent.replace(/[\d.,]+\s*(bpm|mm|cm|ml|%|sa)?/i, mVal[2].trim());
      } else {
        best.textContent = polishStatement(stmt);
      }
      if (isPatho) best.style.cssText = ""; // la mise en forme reste sobre ; le rouge est gere a l'edition manuelle
      replaced++;
    } else if (ul) {
      const li = document.createElement("li");
      li.textContent = polishStatement(stmt);
      ul.appendChild(li);
      added++;
    }
    if (isPatho) anomalies.push(polishStatement(stmt).replace(/\.$/, ""));
  }
  // Conclusion heuristique : des anomalies dictees + une conclusion encore "normale" -> on la reecrit.
  if (anomalies.length && conclP && /normal/i.test(conclusion)) {
    conclP.textContent = anomalies.join(". ") + ". Le reste de l'examen est sans particularité.";
  }
  runQualityChecks();
  return { replaced, added };
}

/* ------------------------------------------------------------------ MODIFIER AU LIEU D'AJOUTER */
const MATCH_STOP = new Set(["le", "la", "les", "de", "des", "du", "un", "une", "et", "au", "aux", "en", "pas", "est", "sans", "avec", "sur", "dans", "ni", "ou", "son", "ses", "leur", "que", "qui"]);
function normalizeForMatch(v) {
  return String(v || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-z0-9\s]/g, " ").replace(/\s+/g, " ").trim();
}
function keyTokens(v) { return normalizeForMatch(v).split(" ").filter((w) => w.length > 2 && !MATCH_STOP.has(w)); }
function similarity(a, b) {
  const A = new Set(keyTokens(a)), B = new Set(keyTokens(b));
  if (!A.size || !B.size) return 0;
  let inter = 0; A.forEach((w) => { if (B.has(w)) inter += 1; });
  return inter / Math.min(A.size, B.size);
}
function insertIntoSection(sectionTitle, text) {
  const editor = $("reportEditor");
  const headings = [...editor.querySelectorAll("h3")];
  const heading = headings.find((h) => h.textContent.trim().toLowerCase() === sectionTitle.toLowerCase());
  if (!heading) { editor.insertAdjacentHTML("beforeend", `<h3>${sectionTitle}</h3><p>${escapeHtml(text)}</p>`); return; }
  if (sectionTitle === "Resultats") {
    let ul = heading.nextElementSibling?.tagName === "UL" ? heading.nextElementSibling : null;
    if (!ul) { heading.insertAdjacentHTML("afterend", `<ul><li>${escapeHtml(text)}</li></ul>`); return; }
    let best = null, bestScore = 0;
    const direct = findLineBySharedMedicalTerm([...ul.querySelectorAll("li")], text);
    if (direct) { direct.textContent = text; return; }
    [...ul.querySelectorAll("li")].forEach((li) => {
      const s = similarity(text, li.textContent);
      if (s > bestScore) { bestScore = s; best = li; }
    });
    if (best && bestScore >= 0.5) best.textContent = text;
    else ul.insertAdjacentHTML("beforeend", `<li>${escapeHtml(text)}</li>`);
  } else {
    const p = heading.nextElementSibling;
    if (p && p.tagName === "P") {
      if (!p.textContent.trim() || similarity(text, p.textContent) >= 0.4) p.textContent = text;
      else p.insertAdjacentHTML("afterend", `<p>${escapeHtml(text)}</p>`);
    } else heading.insertAdjacentHTML("afterend", `<p>${escapeHtml(text)}</p>`);
  }
}

function findLineBySharedMedicalTerm(nodes, dictatedText) {
  const heard = new Set(keyTokens(dictatedText).filter((w) => w.length >= 5));
  if (!heard.size) return null;
  let best = null, bestHits = 0;
  nodes.forEach((node) => {
    const hits = keyTokens(node.textContent).filter((w) => heard.has(w) || [...heard].some((h) => h.includes(w) || w.includes(h))).length;
    if (hits > bestHits) { bestHits = hits; best = node; }
  });
  return bestHits > 0 ? best : null;
}

/* ------------------------------------------------------------------ HISTORIQUE / SAUVEGARDE */
async function saveReport() {
  const data = patientData();
  if (!$("reportEditor").innerHTML.trim()) generateReport();
  const record = { id: crypto.randomUUID(), createdAt: new Date().toISOString(), data, report: $("reportEditor").innerHTML, image: state.currentImageData };
  const records = getRecords();
  records.unshift(record);
  localStorage.setItem("radassist.records", JSON.stringify(records.slice(0, 100)));
  await putRecord(record);
  renderHistory(); runQualityChecks();
}
function getRecords() { return JSON.parse(localStorage.getItem("radassist.records") || "[]"); }
function dayKey(iso) { return String(iso).slice(0, 10); }
function dayLabel(key) {
  const d = new Date(key + "T12:00:00");
  return d.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric" });
}
async function renderHistory() {
  const records = await readAllRecords();
  if (!records.length) {
    $("historyList").innerHTML = `<div class="history-card">Aucun compte rendu enregistre pour le moment.</div>`;
    return;
  }
  // Dossiers par journee : chaque jour regroupe ses comptes rendus.
  const byDay = new Map();
  records.forEach((r) => {
    const k = dayKey(r.createdAt);
    if (!byDay.has(k)) byDay.set(k, []);
    byDay.get(k).push(r);
  });
  $("historyList").innerHTML = [...byDay.entries()].map(([k, list]) => `
    <div class="history-day">
      <div class="history-day-head">
        <strong>${escapeHtml(dayLabel(k))}</strong>
        <span>${list.length} compte(s) rendu(s)</span>
        <span style="flex:1"></span>
        <button class="btn secondary" onclick="exportDayWord('${k}')" title="Tous les CR du jour dans un seul document Word">Exporter la journee (Word)</button>
        <button class="btn ghost" onclick="exportDayArchive('${k}')" title="Archive re-importable sur un autre poste">Archive .json</button>
      </div>
      ${list.map((r) => `
      <div class="history-card">
        <strong>${escapeHtml(r.data.lastName || "Patient sans nom")} ${escapeHtml(r.data.firstName || "")}</strong>
        <p>${escapeHtml(r.data.hospital || "")} — ${escapeHtml(r.data.exam)} — ${new Date(r.createdAt).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" })}</p>
        <div class="card-actions">
          <button class="btn secondary" onclick="loadRecord('${r.id}')">Rouvrir</button>
          <button class="btn danger" onclick="deleteRecordUI('${r.id}')">Supprimer</button>
        </div>
      </div>`).join("")}
    </div>`).join("");
}

async function recordsOfDay(k) { return (await readAllRecords()).filter((r) => dayKey(r.createdAt) === k); }

// Un document Word unique contenant tous les CR de la journee, separes par saut de page.
window.exportDayWord = async function (k) {
  const list = await recordsOfDay(k);
  if (!list.length) return;
  const body = list.map((r) => `<div style="page-break-after:always;">${r.report || ""}</div>`).join("");
  const html = `<!doctype html><html><head><meta charset="utf-8"><title>CR du ${k}</title><style>body,.cr-paper,.cr-paper *{font-family:${REPORT_FONT}!important;} body{margin:36px;color:#111827;line-height:1.5;font-size:${REPORT_SIZE};} h2{text-align:center;text-transform:uppercase;} ${letterheadCss()}</style></head><body>${body}</body></html>`;
  downloadBlob(new Blob([html], { type: "application/msword" }), `comptes-rendus-${k}.doc`);
};

// Archive JSON re-importable (transfert vers un autre poste / sauvegarde).
window.exportDayArchive = async function (k) {
  const list = await recordsOfDay(k);
  const payload = { app: "radassist", kind: "records", day: k, exportedAt: new Date().toISOString(), records: list };
  downloadBlob(new Blob([JSON.stringify(payload)], { type: "application/json" }), `radassist-archive-${k}.json`);
};

window.deleteRecordUI = async function (id) {
  if (!confirm("Supprimer definitivement ce compte rendu ?")) return;
  localStorage.setItem("radassist.records", JSON.stringify(getRecords().filter((r) => r.id !== id)));
  if (state.db) {
    await new Promise((res) => { const tx = state.db.transaction("records", "readwrite"); tx.objectStore("records").delete(id); tx.oncomplete = res; tx.onerror = res; });
  }
  renderHistory();
};

// Import d'une archive .json dans l'historique (fusion sans doublon).
async function importHistoryArchive(file) {
  try {
    const payload = JSON.parse(await file.text());
    const list = Array.isArray(payload) ? payload : (payload.records || []);
    if (!list.length) { alert("Archive vide ou invalide."); return; }
    const existing = new Set((await readAllRecords()).map((r) => r.id));
    let added = 0;
    for (const r of list) {
      if (!r.id || !r.report || existing.has(r.id)) continue;
      await putRecord(r);
      const ls = getRecords(); ls.unshift(r);
      localStorage.setItem("radassist.records", JSON.stringify(ls.slice(0, 100)));
      added++;
    }
    renderHistory();
    alert(`${added} compte(s) rendu(s) importe(s) dans l'historique.`);
  } catch (e) { alert("Import impossible : " + e.message); }
}
function setupHistoryTools() {
  $("historyImportBtn")?.addEventListener("click", () => $("historyImportInput").click());
  $("historyImportInput")?.addEventListener("change", (ev) => {
    const f = ev.target.files?.[0];
    if (f) importHistoryArchive(f);
    ev.target.value = "";
  });
}
window.loadRecord = async function loadRecord(id) {
  const r = (await readAllRecords()).find((x) => x.id === id);
  if (!r) return;
  $("hospitalSelect").value = state.hospitals.find((h) => h.name === r.data.hospital)?.id || state.hospitals[0].id;
  hydrateExams();
  const m = (selectedHospital().exams || []).find((e) => e.title === r.data.exam);
  if (m) $("examSelect").value = m.id;
  $("sideSelect").value = r.data.side || "";
  $("dateInput").value = r.data.date || todayIso();
  $("lastNameInput").value = r.data.lastName || "";
  $("firstNameInput").value = r.data.firstName || "";
  $("ageInput").value = r.data.age || "";
  $("sexInput").value = r.data.sex || "";
  $("doctorInput").value = r.data.doctor || "";
  $("recordInput").value = r.data.record || "";
  $("reportEditor").innerHTML = r.report || "";
  if (r.image) { state.currentImageData = r.image; $("previewImage").src = r.image; $("previewImage").hidden = false; }
  document.querySelector('[data-view="workspace"]').click();
  runQualityChecks();
};
function resetCase() {
  ["lastNameInput", "firstNameInput", "ageInput", "doctorInput", "recordInput", "voiceText", "ocrText"].forEach((id) => ($(id).value = ""));
  $("sexInput").value = ""; $("sideSelect").value = ""; $("dateInput").value = todayIso();
  $("imageInput").value = ""; $("previewImage").hidden = true; state.currentImageData = ""; state.voiceBase = ""; state.lastAppliedVoice = "";
  $("ocrStatus").textContent = "En attente du texte du bulletin.";
  generateReport(); runQualityChecks();
}

/* ------------------------------------------------------------------ EXPORTS / IMPRESSION */
function exportWord() {
  if (!$("reportEditor").innerHTML.trim()) generateReport();
  const html = `<!doctype html><html><head><meta charset="utf-8"><title>Compte rendu</title><style>body,.report-editor,.cr-paper,.cr-paper *{font-family:${REPORT_FONT}!important;} body{margin:36px;color:#111827;line-height:1.5;font-size:${REPORT_SIZE};} h2{text-align:center;text-transform:uppercase;} h3{border-bottom:none;} ${letterheadCss()}</style></head><body>${$("reportEditor").innerHTML}</body></html>`;
  downloadBlob(new Blob([html], { type: "application/msword" }), fileBaseName() + ".doc");
}
function exportPdf() { printReport(); }
function setupPrintButton() {
  const toolbar = document.querySelector(".toolbar");
  if (!toolbar || $("printBtn")) return;
  const btn = document.createElement("button");
  btn.className = "icon-btn"; btn.id = "printBtn"; btn.title = "Imprimer le compte rendu"; btn.textContent = "Imprimer";
  toolbar.appendChild(btn);
  btn.addEventListener("click", printReport);
}
// CSS partage pour que l'en-tete (letterhead) s'affiche a l'identique a l'ecran,
// a l'impression et dans Word.
function letterheadCss() {
  return `
    .cr-paper { font-family: ${REPORT_FONT}; font-size: ${REPORT_SIZE}; }
    .cr-paper p, .cr-paper li { font-size: ${REPORT_SIZE}; }
    .cr-nk-top { display:flex; align-items:flex-start; gap:16px; margin-bottom:6px; }
    .cr-nk-logo { width:66px; height:auto; object-fit:contain; flex:0 0 auto; }
    .cr-nk-titles { padding-top:6px; }
    .cr-nk-name { color:#45767B; font-family:Arial,Helvetica,sans-serif; font-weight:800; font-size:18px; line-height:1.15; }
    .cr-nk-doc { color:#6b7f82; font-family:Arial,Helvetica,sans-serif; font-size:13px; margin-top:2px; }
    .cr-nk-body { display:grid; grid-template-columns:120px 1fr; gap:20px; align-items:start; }
    .cr-nk-band { width:115px; height:auto; object-fit:contain; }
    .cr-nk-main { min-width:0; }
    /* Compat anciens rendus */
    .cr-left-letterhead { display:grid; grid-template-columns:120px 1fr; gap:18px; }
    .cr-left-rail { border-right:2px solid #45767B; padding:2px 12px 2px 0; color:#5b6b73; font-size:10px; line-height:1.5; text-align:center; }
    .cr-main-letterhead { min-width:0; }
    .cr-header-title { color:#45767B; font-weight:800; text-align:center; text-transform:uppercase; font-size:17px; line-height:1.2; }`;
}

function printReport() {
  if (!$("reportEditor").innerHTML.trim()) generateReport();
  const win = window.open("", "_blank");
  if (!win) { window.print(); return; }
  const doc = `<!doctype html><html><head><meta charset="utf-8"><title>Compte rendu</title>
    <style>
      @page { size: A4; margin: 0; }
      html, body { margin: 0; padding: 0; }
      body { font-family: ${REPORT_FONT}; font-size:${REPORT_SIZE}; color:#111827; padding:15mm 16mm; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      .report { max-width: 780px; margin: 0 auto; line-height: 1.55; }
      h2 { text-align:center; text-transform:uppercase; font-size:18px; }
      h3 { margin-top:20px; padding-bottom:4px; border-bottom:none; }
      ${letterheadCss()}
    </style></head><body><main class="report">${$("reportEditor").innerHTML}</main></body></html>`;
  win.document.open(); win.document.write(doc); win.document.close();
  win.focus();
  setTimeout(() => win.print(), 350);
}
function fileBaseName() {
  const d = patientData();
  return `${d.lastName || "patient"}-${d.firstName || ""}-${d.exam || "compte-rendu"}`
    .toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
}
function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a"); a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}

/* ------------------------------------------------------------------ TEMPLATES (onglet)
   Navigation 2 niveaux :
   - Niveau 1 : liste de TOUS les hopitaux (avec suppression par hopital).
   - Niveau 2 : on "ouvre" un hopital -> ses examens (avec suppression par examen). */
function renderTemplates() {
  const list = $("templateList");
  if (!list) return;
  const openId = state.templatesHospitalId;
  const h = openId ? state.hospitals.find((x) => x.id === openId) : null;

  if (!h) {
    state.templatesHospitalId = null;
    list.innerHTML = `<p class="templates-hint">Cliquez sur un hopital pour voir et gerer ses examens.</p>` +
      (state.hospitals.map((hop) => `
        <div class="template-card">
          <div><strong>${escapeHtml(hop.name)}</strong><p>${(hop.exams || []).length} examen(s)</p></div>
          <div class="card-actions">
            <button class="btn secondary" onclick="openHospitalTemplates('${hop.id}')">Ouvrir</button>
            <button class="btn danger" onclick="deleteHospitalUI('${hop.id}')">Supprimer</button>
          </div>
        </div>`).join("") || `<div class="template-card">Aucun hopital.</div>`);
    return;
  }

  const head = `
    <div class="template-head">
      <button class="btn ghost" onclick="closeHospitalTemplates()">&larr; Hopitaux</button>
      <div style="flex:1;text-align:center;"><strong>${escapeHtml(h.name)}</strong><span>${(h.exams || []).length} examen(s)</span></div>
      <button class="btn danger" onclick="deleteHospitalUI('${h.id}')">Supprimer l'hopital</button>
    </div>`;
  const cards = (h.exams || []).map((e) => `
    <div class="template-card">
      <div><strong>${escapeHtml(e.title)}</strong><p>${escapeHtml(e.technique || e.conclusion || "")}</p></div>
      <div class="card-actions">
        <button class="btn secondary" onclick="selectTemplate('${e.id}','${h.id}')">Utiliser</button>
        <button class="btn ghost" onclick="editExamUI('${h.id}','${e.id}')" title="Modifier ce template">Modifier</button>
        <button class="btn danger" onclick="deleteExamUI('${h.id}','${e.id}')" title="Supprimer cet examen">Supprimer</button>
      </div>
    </div>`).join("") || `<div class="template-card">Aucun examen. Utilisez "+ Examen" dans le dossier.</div>`;
  list.innerHTML = head + cards;
}
window.openHospitalTemplates = function (id) { state.templatesHospitalId = id; renderTemplates(); };
window.closeHospitalTemplates = function () { state.templatesHospitalId = null; renderTemplates(); };

window.editExamUI = function editExamUI(hospitalId, examId) {
  const h = state.hospitals.find((x) => x.id === hospitalId);
  const e = h?.exams.find((x) => x.id === examId);
  if (!e) return;
  const html = `
    <p style="margin:0;color:#475569;">Hopital : <strong>${escapeHtml(h.name)}</strong></p>
    <label>Titre de l'examen <input id="edTitle" /></label>
    <label>Titre du compte rendu (en-tete) <input id="edHeading" /></label>
    <label><input id="edSide" type="checkbox" style="width:auto;margin-right:6px;" />Examen avec cote (D/G)</label>
    <label>Technique <textarea id="edTech" rows="2"></textarea></label>
    <label>Resultats (une ligne par item) <textarea id="edRes" rows="6"></textarea></label>
    <label>Conclusion <textarea id="edConcl" rows="3"></textarea></label>`;
  const back = showModal("Modifier le template", html, (root) => {
    const title = root.querySelector("#edTitle").value.trim();
    if (!title) { root.querySelector("#edTitle").focus(); return false; }
    const fields = {
      title,
      heading: root.querySelector("#edHeading").value.trim() || `Compte Rendu de ${title}`,
      requiresSide: root.querySelector("#edSide").checked ? "side" : "",
      technique: root.querySelector("#edTech").value.trim(),
      results: root.querySelector("#edRes").value.split("\n").map((s) => s.trim()).filter(Boolean),
      conclusion: root.querySelector("#edConcl").value.trim()
    };
    saveExamOverride(hospitalId, examId, fields);
    loadHospitals();
    state.templatesHospitalId = hospitalId;
    const keep = $("hospitalSelect").value;
    if ([...$("hospitalSelect").options].some((o) => o.value === keep)) { $("hospitalSelect").value = keep; }
    hydrateExams();
    if ($("examSelect").value === examId || [...$("examSelect").options].some((o) => o.value === examId)) {
      $("examSelect").value = examId;
    }
    renderTemplates();
    generateReport();
    runQualityChecks();
  });
  // Pre-remplir les champs avec les valeurs actuelles.
  back.querySelector("#edTitle").value = e.title || "";
  back.querySelector("#edHeading").value = e.heading || "";
  back.querySelector("#edSide").checked = !!e.requiresSide;
  back.querySelector("#edTech").value = e.technique || "";
  back.querySelector("#edRes").value = (e.results || []).join("\n");
  back.querySelector("#edConcl").value = e.conclusion || "";
};

window.selectTemplate = function selectTemplate(id, hospitalId) {
  if (hospitalId && $("hospitalSelect").value !== hospitalId) { $("hospitalSelect").value = hospitalId; hydrateExams(); }
  $("examSelect").value = id;
  document.querySelector('[data-view="workspace"]').click();
  generateReport();
};
window.deleteExamUI = function deleteExamUI(hospitalId, examId) {
  const h = state.hospitals.find((x) => x.id === hospitalId);
  const ex = h?.exams.find((e) => e.id === examId);
  if (!confirm(`Supprimer l'examen "${ex ? ex.title : examId}" ?`)) return;
  const keep = $("hospitalSelect").value;
  deleteExam(hospitalId, examId);
  loadHospitals();
  state.templatesHospitalId = hospitalId;               // rester dans l'hopital ouvert
  if ([...$("hospitalSelect").options].some((o) => o.value === keep)) { $("hospitalSelect").value = keep; }
  hydrateExams(); renderTemplates(); generateReport(); runQualityChecks();
};
window.deleteHospitalUI = function deleteHospitalUI(hospitalId) {
  const h = state.hospitals.find((x) => x.id === hospitalId);
  if (state.hospitals.length <= 1) { alert("Impossible de supprimer le dernier hopital."); return; }
  if (!confirm(`Supprimer l'hopital "${h ? h.name : hospitalId}" et tous ses examens ? (annulable en reimportant une configuration)`)) return;
  const keep = $("hospitalSelect").value;
  deleteHospital(hospitalId);
  loadHospitals();
  state.templatesHospitalId = null;                     // retour a la liste des hopitaux
  hydrateHospitals();
  if (keep !== hospitalId && [...$("hospitalSelect").options].some((o) => o.value === keep)) {
    $("hospitalSelect").value = keep; hydrateExams();
  }
  renderTemplates(); generateReport(); runQualityChecks();
};

/* ------------------------------------------------------------------ GESTION HOPITAUX / EXAMENS */
// Bouton "Ajouter hopital" a cote de "Nouveau dossier" (barre du haut).
function setupTopActions() {
  const actions = document.querySelector(".top-actions");
  if (!actions || $("addHospitalBtn")) return;
  const btn = document.createElement("button");
  btn.className = "btn secondary"; btn.id = "addHospitalBtn"; btn.textContent = "Ajouter hopital";
  const reset = $("resetBtn");
  if (reset) actions.insertBefore(btn, reset); else actions.appendChild(btn);
  btn.addEventListener("click", openHospitalModal);
}

// Petit bouton "+ Examen" a cote de la liste des examens (contextuel, pas de barre).
function setupExamAddButton() {
  const sel = $("examSelect");
  if (!sel || $("addExamBtn")) return;
  const btn = document.createElement("button");
  btn.type = "button"; btn.className = "btn ghost"; btn.id = "addExamBtn"; btn.textContent = "+ Examen";
  btn.title = "Ajouter un examen a cet hopital";
  btn.style.cssText = "margin-top:6px;padding:6px 10px;font-size:13px;";
  sel.insertAdjacentElement("afterend", btn);
  btn.addEventListener("click", openExamModal);
}

// Importer / Exporter la configuration : dans l'onglet Parametres.
function setupImportExport() {
  const grid = document.querySelector("#settings .settings-grid");
  if (!grid || $("exportBtn")) return;
  const block = document.createElement("div");
  block.style.gridColumn = "1 / -1";
  block.innerHTML = `
    <strong>Sauvegarde des hopitaux/examens ajoutes</strong>
    <span>Exporter pour transferer vos ajouts vers un autre poste, ou importer une configuration.</span>
    <div style="display:flex;gap:8px;margin-top:8px;">
      <button class="btn secondary" id="exportBtn">Exporter</button>
      <button class="btn secondary" id="importBtn">Importer</button>
      <input id="importFile" type="file" accept="application/json,.json" hidden />
    </div>`;
  grid.appendChild(block);
  $("exportBtn").addEventListener("click", exportConfig);
  $("importBtn").addEventListener("click", () => $("importFile").click());
  $("importFile").addEventListener("change", (e) => { if (e.target.files[0]) importConfig(e.target.files[0]); });
}

function setupTemplateImport() {
  const grid = document.querySelector("#settings .settings-grid");
  if (!grid || $("templateDocxFile")) return;
  const block = document.createElement("div");
  block.style.gridColumn = "1 / -1";
  block.innerHTML = `
    <strong>Importer un template Word en nouvel hopital</strong>
    <span>Importe un .docx, extrait les examens, puis ajoute l'hopital dans la bibliotheque comme les hopitaux existants.</span>
    <input id="templateHospitalName" placeholder="Nom de l'hopital a creer" style="margin-top:8px;" />
    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
      <input id="templateDocxFile" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" />
      <button class="btn secondary" id="importTemplateBtn" type="button">Analyser et importer</button>
    </div>
    <span id="templateImportStatus">En attente d'un fichier Word.</span>`;
  grid.appendChild(block);
  $("importTemplateBtn").addEventListener("click", importTemplateDocxFromSettings);
}

function setupAiDraft() {
  $("aiDraftBtn")?.addEventListener("click", generateAiDraft);
}

/* =========================================================================
   Assistant de recherche Claude — chat integre
   ========================================================================= */
const CLAUDE_MODELS = [
  { id: "claude-sonnet-4-6", label: "Claude Sonnet 4.6 (equilibre, recommande)" },
  { id: "claude-opus-4-8", label: "Claude Opus 4.8 (qualite maximale)" },
  { id: "claude-haiku-4-5-20251001", label: "Claude Haiku 4.5 (rapide et economique)" }
];

const CLAUDE_SYSTEM_PROMPT =
  "Tu es un assistant expert pour un radiologue exercant au Cameroun (contexte Afrique centrale, plateaux techniques variables). " +
  "Reponds en francais, de facon precise, structuree et concise. Tu sais faire deux choses : " +
  "(1) repondre a des questions cliniques/radiologiques (indications, protocoles, semiologie, diagnostics differentiels, classifications comme BI-RADS/TI-RADS/LI-RADS/Bosniak/PI-RADS, conduites a tenir, recommandations recentes) ; " +
  "(2) rediger des comptes rendus radiologiques professionnels complets (Identification, Technique, Resultats, Conclusion) quand on te le demande, y compris des comptes rendus normaux d'un examen donne. " +
  "Quand des fichiers sont joints (image de radio/scanner/echo, ou PDF de bulletin/protocole), analyse-les et exploite-les. " +
  "Utilise du Markdown clair (titres, listes, gras). Cite tes sources quand tu utilises la recherche web. " +
  "N'invente jamais de donnee patient ni de reference ; en cas de doute, dis-le. Rappelle que la validation finale revient au medecin.";

function setupClaudeSettings() {
  const grid = document.querySelector("#settings .settings-grid");
  if (!grid || $("claudeKeyInput")) return;
  const block = document.createElement("div");
  block.style.gridColumn = "1 / -1";
  const options = CLAUDE_MODELS.map((m) => `<option value="${m.id}">${m.label}</option>`).join("");
  block.innerHTML = `
    <strong>Assistant de recherche (Claude)</strong>
    <span>Cle API Anthropic. Elle alimente l'assistant IA, la lecture automatique du bulletin patient et la generation de comptes rendus. La cle reste sur cet appareil (localStorage). Pour un deploiement public multi-utilisateurs, renseignez plutot une URL de proxy serveur.</span>
    <input id="claudeKeyInput" type="password" placeholder="sk-ant-..." autocomplete="off" style="margin-top:8px;" />
    <select id="claudeModelInput" style="margin-top:8px;">${options}</select>
    <input id="claudeProxyInput" type="text" placeholder="Optionnel : URL de proxy serveur (ex: https://mon-serveur/anthropic)" autocomplete="off" style="margin-top:8px;" />`;
  grid.appendChild(block);
  const k = block.querySelector("#claudeKeyInput");
  const m = block.querySelector("#claudeModelInput");
  const p = block.querySelector("#claudeProxyInput");
  k.value = localStorage.getItem("radassist.claudeKey") || "";
  m.value = localStorage.getItem("radassist.claudeModel") || "claude-sonnet-4-6";
  p.value = localStorage.getItem("radassist.claudeProxy") || "";
  k.addEventListener("change", () => localStorage.setItem("radassist.claudeKey", k.value.trim()));
  m.addEventListener("change", () => localStorage.setItem("radassist.claudeModel", m.value));
  p.addEventListener("change", () => localStorage.setItem("radassist.claudeProxy", p.value.trim()));
}

function setupAssistant() {
  $("chatSendBtn")?.addEventListener("click", sendChatMessage);
  $("chatInput")?.addEventListener("keydown", (e) => {
    if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendChatMessage(); }
  });
  $("assistantClearBtn")?.addEventListener("click", clearChat);
  $("assistantSuggestions")?.querySelectorAll(".chip").forEach((chip) => {
    chip.addEventListener("click", () => {
      $("chatInput").value = chip.textContent.trim();
      sendChatMessage();
    });
  });
  $("chatFileInput")?.addEventListener("change", handleChatFiles);
  $("chatVoiceBtn")?.addEventListener("click", toggleChatVoice);
}

// Chargement paresseux de mammoth.js (extraction de texte des .docx) depuis un CDN.
let mammothPromise = null;
function loadMammoth() {
  if (window.mammoth) return Promise.resolve(window.mammoth);
  if (mammothPromise) return mammothPromise;
  mammothPromise = new Promise((resolve, reject) => {
    const s = document.createElement("script");
    s.src = "https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js";
    s.onload = () => window.mammoth ? resolve(window.mammoth) : reject(new Error("mammoth non charge"));
    s.onerror = () => reject(new Error("telechargement de l'extracteur Word impossible (hors ligne ?)"));
    document.head.appendChild(s);
  });
  return mammothPromise;
}

function fileToArrayBuffer(file) {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(r.result);
    r.onerror = () => reject(new Error("lecture fichier"));
    r.readAsArrayBuffer(file);
  });
}
function fileToText(file) {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(String(r.result));
    r.onerror = () => reject(new Error("lecture fichier"));
    r.readAsText(file);
  });
}

async function handleChatFiles(event) {
  const files = Array.from(event.target.files || []);
  for (const file of files) {
    const name = file.name || "fichier";
    const lower = name.toLowerCase();
    try {
      if (file.type === "application/pdf" || lower.endsWith(".pdf")) {
        const data = await fileToBase64(file);
        state.chatAttachments.push({ name, kind: "pdf", mediaType: "application/pdf", data });
      } else if (file.type && /^image\//.test(file.type)) {
        const data = await fileToBase64(file);
        state.chatAttachments.push({ name, kind: "image", mediaType: file.type, data });
      } else if (lower.endsWith(".docx")) {
        setAssistantStatus("Extraction du texte du fichier Word...");
        const mammoth = await loadMammoth();
        const buf = await fileToArrayBuffer(file);
        const out = await mammoth.extractRawText({ arrayBuffer: buf });
        state.chatAttachments.push({ name, kind: "text", text: out.value || "" });
        setAssistantStatus("");
      } else if (/\.(txt|csv|md|json|xml|htm|html|rtf)$/i.test(lower) || (file.type && /^text\//.test(file.type))) {
        const text = await fileToText(file);
        state.chatAttachments.push({ name, kind: "text", text });
      } else if (lower.endsWith(".doc")) {
        setAssistantStatus("Le format .doc (ancien Word) n'est pas lisible ici : enregistrez le fichier en .docx et reimportez.", true);
      } else {
        setAssistantStatus(`Format non pris en charge : ${name}. Formats acceptes : images, PDF, Word (.docx), texte.`, true);
      }
    } catch (err) {
      setAssistantStatus(`Fichier "${name}" non traite : ${err.message}`, true);
    }
  }
  event.target.value = "";
  renderChatAttachments();
}

function renderChatAttachments() {
  const box = $("chatAttachments");
  if (!box) return;
  box.innerHTML = state.chatAttachments.map((a, i) => {
    const label = a.kind === "pdf" ? "PDF" : a.kind === "text" ? "Texte" : "Image";
    return `<span class="attach-chip">${label} : ${escapeHtml(a.name)}<button type="button" data-i="${i}" title="Retirer">×</button></span>`;
  }).join("");
  box.querySelectorAll("button[data-i]").forEach((b) => {
    b.addEventListener("click", () => {
      state.chatAttachments.splice(Number(b.dataset.i), 1);
      renderChatAttachments();
    });
  });
}

function toggleChatVoice() {
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { setAssistantStatus("La dictee vocale n'est pas disponible dans ce navigateur (essayez Chrome/Edge).", true); return; }
  if (state.chatListening && state.chatRecognition) { state.chatRecognition.stop(); return; }
  const rec = new SR();
  rec.lang = "fr-FR"; rec.continuous = true; rec.interimResults = false;
  const base = $("chatInput").value;
  rec.onresult = (ev) => {
    let add = "";
    for (let i = ev.resultIndex; i < ev.results.length; i++) add += ev.results[i][0].transcript;
    $("chatInput").value = (base ? base + " " : "") + add.trim();
  };
  rec.onend = () => { state.chatListening = false; $("chatVoiceBtn").classList.remove("recording"); $("chatVoiceBtn").textContent = "Dicter"; };
  rec.onerror = () => { setAssistantStatus("Micro indisponible ou permission refusee.", true); };
  state.chatRecognition = rec; state.chatListening = true;
  $("chatVoiceBtn").classList.add("recording"); $("chatVoiceBtn").textContent = "Stop";
  rec.start();
}

function clearChat() {
  state.assistantHistory = [];
  state.chatAttachments = [];
  renderChatAttachments();
  const thread = $("chatThread");
  thread.innerHTML = `<div class="chat-empty" id="chatEmpty">Aucune conversation pour l'instant. Posez votre premiere question ci-dessous.</div>`;
  setAssistantStatus("");
}

function setAssistantStatus(msg, isError) {
  const el = $("assistantStatus");
  if (!el) return;
  el.textContent = msg;
  el.className = "assistant-status" + (isError ? " error" : "");
}

function appendChatMessage(role, htmlContent, sources, rawText) {
  const thread = $("chatThread");
  $("chatEmpty")?.remove();
  const wrap = document.createElement("div");
  wrap.className = "chat-msg " + role;
  const roleLabel = role === "user" ? "Vous" : "Claude";
  let sourcesHtml = "";
  if (sources && sources.length) {
    const items = sources.map((s) =>
      `<a href="${escapeAttr(s.url)}" target="_blank" rel="noopener">${escapeHtml(s.title || s.url)}</a>`
    ).join("");
    sourcesHtml = `<div class="chat-sources"><strong>Sources</strong>${items}</div>`;
  }
  wrap.innerHTML = `<span class="chat-role">${roleLabel}</span><div class="chat-bubble">${htmlContent}</div>${sourcesHtml}`;
  if (role === "assistant" && rawText) {
    const actions = document.createElement("div");
    actions.className = "chat-msg-actions";
    actions.innerHTML = `<button class="btn ghost" data-exp="word">Exporter Word</button><button class="btn ghost" data-exp="pdf">Exporter PDF</button><button class="btn ghost" data-exp="copy">Copier</button>`;
    actions.querySelector('[data-exp="word"]').addEventListener("click", () => exportAnswerToWord(htmlContent));
    actions.querySelector('[data-exp="pdf"]').addEventListener("click", () => exportAnswerToPdf(htmlContent));
    actions.querySelector('[data-exp="copy"]').addEventListener("click", () => navigator.clipboard?.writeText(rawText));
    wrap.appendChild(actions);
  }
  thread.appendChild(wrap);
  thread.scrollTop = thread.scrollHeight;
  return wrap;
}

function answerDocumentHtml(innerHtml) {
  return `<!doctype html><html><head><meta charset="utf-8"><title>Compte rendu</title>
    <style>
      @page { size: A4; margin: 0; }
      html,body{margin:0;padding:0;}
      body{font-family:"Arial Narrow",Arial,sans-serif;font-size:11pt;color:#111827;padding:15mm 16mm;line-height:1.5;}
      h1,h2,h3{margin:14px 0 6px;}
    </style></head><body>${innerHtml}</body></html>`;
}

function exportAnswerToWord(innerHtml) {
  const html = `<!doctype html><html><head><meta charset="utf-8"><title>Compte rendu</title>
    <style>body{font-family:"Arial Narro","Arial Narrow",Arial,sans-serif;color:#111827;margin:36px;line-height:1.55;}</style>
    </head><body>${innerHtml}</body></html>`;
  downloadBlob(new Blob([html], { type: "application/msword" }), "compte-rendu-ia.doc");
}

function exportAnswerToPdf(innerHtml) {
  const win = window.open("", "_blank");
  if (!win) return;
  win.document.open();
  win.document.write(answerDocumentHtml(innerHtml));
  win.document.close();
  win.focus();
  setTimeout(() => win.print(), 300);
}

function showTyping() {
  const thread = $("chatThread");
  const wrap = document.createElement("div");
  wrap.className = "chat-msg assistant";
  wrap.id = "chatTyping";
  wrap.innerHTML = `<span class="chat-role">Claude</span><div class="chat-bubble"><div class="chat-typing"><span></span><span></span><span></span></div></div>`;
  thread.appendChild(wrap);
  thread.scrollTop = thread.scrollHeight;
}
function removeTyping() { $("chatTyping")?.remove(); }

async function sendChatMessage() {
  if (state.assistantBusy) return;
  const input = $("chatInput");
  const text = input.value.trim();
  if (!text && !state.chatAttachments.length) return;

  if (!hasClaude()) {
    setAssistantStatus("Ajoutez votre cle API Anthropic (ou une URL de proxy) dans Parametres pour activer l'assistant.", true);
    return;
  }

  // Construire le contenu (texte + pieces jointes) pour l'API et pour l'affichage.
  const attachments = state.chatAttachments.slice();
  const apiContent = [];
  const textDocs = [];
  attachments.forEach((a) => {
    if (a.kind === "pdf") {
      apiContent.push({ type: "document", source: { type: "base64", media_type: "application/pdf", data: a.data } });
    } else if (a.kind === "image") {
      apiContent.push({ type: "image", source: { type: "base64", media_type: a.mediaType, data: a.data } });
    } else if (a.kind === "text") {
      textDocs.push(`--- Contenu du fichier "${a.name}" ---\n${a.text}`);
    }
  });
  const composed = [textDocs.join("\n\n"), text].filter(Boolean).join("\n\n") || "Analyse le document joint.";
  apiContent.push({ type: "text", text: composed });

  input.value = "";
  state.chatAttachments = [];
  renderChatAttachments();

  const attachLabel = attachments.length
    ? `<div class="chat-attach-note">${attachments.length} fichier(s) joint(s)</div>` : "";
  appendChatMessage("user", attachLabel + escapeHtml(text).replace(/\n/g, "<br>"));
  state.assistantHistory.push({ role: "user", content: apiContent });

  state.assistantBusy = true;
  $("chatSendBtn").disabled = true;
  setAssistantStatus("");
  showTyping();

  try {
    const result = await callClaude();
    removeTyping();
    appendChatMessage("assistant", renderMarkdown(result.text), result.sources, result.text);
    state.assistantHistory.push({ role: "assistant", content: result.text });
    // Borner le contexte : conserver les 12 derniers echanges (24 messages), en gardant un tour utilisateur en tete.
    if (state.assistantHistory.length > 24) {
      state.assistantHistory = state.assistantHistory.slice(-24);
      while (state.assistantHistory.length && state.assistantHistory[0].role !== "user") {
        state.assistantHistory.shift();
      }
    }
  } catch (error) {
    removeTyping();
    state.assistantHistory.pop();
    setAssistantStatus("Reponse impossible : " + error.message, true);
  } finally {
    state.assistantBusy = false;
    $("chatSendBtn").disabled = false;
  }
}

function claudeCredentials() {
  let model = localStorage.getItem("radassist.claudeModel") || "claude-sonnet-4-6";
  if (!CLAUDE_MODELS.some((m) => m.id === model)) {   // migration des anciens identifiants invalides
    model = "claude-sonnet-4-6";
    localStorage.setItem("radassist.claudeModel", model);
  }
  return {
    proxy: (localStorage.getItem("radassist.claudeProxy") || "").trim(),
    key: (localStorage.getItem("radassist.claudeKey") || "").trim(),
    model
  };
}
function hasClaude() {
  const c = claudeCredentials();
  return !!(c.proxy || c.key);
}

/* Appel generique a l'API Anthropic (assistant, vision bulletin, IA libre). */
async function anthropicRequest(body) {
  const { proxy, key } = claudeCredentials();
  const url = proxy || "https://api.anthropic.com/v1/messages";
  const headers = { "content-type": "application/json" };
  if (!proxy) {
    headers["x-api-key"] = key;
    headers["anthropic-version"] = "2023-06-01";
    headers["anthropic-dangerous-direct-browser-access"] = "true";
  }
  let res;
  try {
    res = await fetch(url, { method: "POST", headers, body: JSON.stringify(body) });
  } catch (netErr) {
    throw new Error(`connexion impossible vers ${url} (reseau/CORS). ${netErr.message}`);
  }
  if (!res.ok) {
    let apiMsg = "";
    try { apiMsg = (await res.json())?.error?.message || ""; } catch (_) {}
    const hint =
      res.status === 401 ? " — cle API invalide ou absente." :
      res.status === 405 ? ` — l'adresse ${url} n'accepte pas POST (proxy mal configure ou URL statique).` :
      res.status === 429 ? " — quota/limite atteint, reessayez plus tard." :
      res.status === 400 ? " — requete refusee (modele indisponible ou format)." : "";
    throw new Error(`HTTP ${res.status}${hint}${apiMsg ? " " + apiMsg : ""}`);
  }
  return res.json();
}

// Helper : envoie un message a Claude et renvoie le texte de la reponse.
async function claudeText(system, userContent, maxTokens) {
  const { model } = claudeCredentials();
  const data = await anthropicRequest({
    model,
    max_tokens: maxTokens || 1800,
    system,
    messages: [{ role: "user", content: userContent }]
  });
  return (data?.content || []).filter((b) => b.type === "text").map((b) => b.text).join("\n");
}

async function callClaude() {
  const { model } = claudeCredentials();
  const useWeb = $("assistantWebToggle")?.checked;
  const body = {
    model,
    max_tokens: 2000,
    system: CLAUDE_SYSTEM_PROMPT,
    messages: state.assistantHistory
  };
  if (useWeb) {
    body.tools = [{ type: "web_search_20250305", name: "web_search", max_uses: 5 }];
  }
  const data = await anthropicRequest(body);
  return parseClaudeResponse(data);
}

function parseClaudeResponse(data) {
  const blocks = Array.isArray(data?.content) ? data.content : [];
  const textParts = [];
  const sourcesMap = new Map();

  for (const block of blocks) {
    if (block.type === "text") {
      textParts.push(block.text || "");
      // Citations attachees au texte (recherche web).
      (block.citations || []).forEach((c) => {
        if (c.url) sourcesMap.set(c.url, { url: c.url, title: c.title || c.url });
      });
    } else if (block.type === "web_search_tool_result") {
      const results = Array.isArray(block.content) ? block.content : [];
      results.forEach((r) => {
        if (r.url) sourcesMap.set(r.url, { url: r.url, title: r.title || r.url });
      });
    }
  }

  let text = textParts.join("\n").trim();
  if (!text) {
    text = data?.stop_reason === "max_tokens"
      ? "Reponse tronquee (limite de longueur atteinte). Reformulez ou demandez une reponse plus courte."
      : "Aucune reponse texte recue.";
  }
  return { text, sources: Array.from(sourcesMap.values()).slice(0, 8) };
}

/* Rendu markdown minimal et sur (echappe le HTML puis applique un sous-ensemble). */
function renderMarkdown(md) {
  const esc = escapeHtml(md);
  const lines = esc.split("\n");
  let html = "";
  let inUl = false;
  let inOl = false;
  const closeLists = () => {
    if (inUl) { html += "</ul>"; inUl = false; }
    if (inOl) { html += "</ol>"; inOl = false; }
  };
  for (let raw of lines) {
    const line = raw.trim();
    if (!line) { closeLists(); continue; }
    if (/^[-*]\s+/.test(line)) {
      if (!inUl) { closeLists(); html += "<ul>"; inUl = true; }
      html += "<li>" + inlineMarkdown(line.replace(/^[-*]\s+/, "")) + "</li>";
    } else if (/^\d+[.)]\s+/.test(line)) {
      if (!inOl) { closeLists(); html += "<ol>"; inOl = true; }
      html += "<li>" + inlineMarkdown(line.replace(/^\d+[.)]\s+/, "")) + "</li>";
    } else if (/^#{1,6}\s+/.test(line)) {
      closeLists();
      html += "<p><strong>" + inlineMarkdown(line.replace(/^#{1,6}\s+/, "")) + "</strong></p>";
    } else {
      closeLists();
      html += "<p>" + inlineMarkdown(line) + "</p>";
    }
  }
  closeLists();
  return html;
}

function inlineMarkdown(s) {
  return s
    .replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>")
    .replace(/`([^`]+)`/g, "<code>$1</code>")
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
}

function escapeAttr(v) {
  return String(v == null ? "" : v).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

async function generateAiDraft() {
  const prompt = $("aiPromptText").value.trim() || $("voiceText").value.trim();
  if (!prompt) { $("aiStatus").textContent = "Dictez ou ecrivez la demande de compte rendu."; return; }
  if (!hasClaude()) { $("aiStatus").textContent = "Ajoutez votre cle API Claude dans Parametres."; return; }

  const { model } = claudeCredentials();
  const hospital = selectedHospital();
  const data = patientData();
  const examples = (hospital?.exams || []).slice(0, 8).map((e) => ({
    title: e.title, heading: e.heading, technique: e.technique, results: e.results, conclusion: e.conclusion
  }));

  const system =
    "Tu es un RADIOLOGUE SENIOR (niveau agrege) qui redige des comptes rendus en francais pour un confrere au Cameroun. Exigences ABSOLUES : " +
    "(1) COMPLETUDE SYSTEMATIQUE : decris TOUTES les structures anatomiquement pertinentes de l'examen, y compris les structures normales notees explicitement (ex. une echographie cervicale decrit toujours thyroide, glandes salivaires, aires ganglionnaires). " +
    "(2) STYLE DE L'HOPITAL : reprends strictement la terminologie, l'ordre et le ton des exemples fournis ; le template de l'examen selectionne, s'il est fourni, sert de squelette a modifier. " +
    "(3) CLASSIFICATIONS : enonce explicitement dans la conclusion toute classification applicable (BI-RADS, EU-TIRADS, Bosniak, Kellgren-Lawrence, PI-RADS, LI-RADS, NASCET, Meyerding, FIGO...). " +
    "(4) ZERO INVENTION : jamais de nom, d'age, de sexe, de lateralite ou de medecin non fournis ; laisse ces champs vides ou en pointilles. Les mesures non fournies restent en pointilles (……… mm). " +
    "(5) CONCLUSION : hierarchisee, repond a la question clinique, propose une recommandation de suivi ou d'imagerie complementaire quand c'est cliniquement justifie. " +
    "(6) LONGUEUR : reste aussi complet que necessaire mais SANS remplissage inutile — un compte rendu senior est precis, pas verbeux. " +
    "Reponds UNIQUEMENT par un JSON strict valide, sans texte ni Markdown autour : " +
    '{"heading":"","technique":"","results":[],"conclusion":""}. ' +
    "results = tableau de lignes d'observations (une structure/idee par ligne, chaine de caracteres simple, jamais d'objet imbrique). heading = titre du compte rendu en MAJUSCULES.";

  const currentTpl = selectedTemplate();
  const baseUserText =
    `Hopital : ${hospital?.name || ""}\n` +
    (currentTpl ? `TEMPLATE DE L'EXAMEN SELECTIONNE (squelette a adapter) : ${JSON.stringify({ heading: currentTpl.heading, technique: currentTpl.technique, results: currentTpl.results, conclusion: currentTpl.conclusion })}\n` : "") +
    `Exemples de style de cet hopital (JSON) : ${JSON.stringify(examples)}\n` +
    `Contexte patient (a ne pas inventer au-dela) : ${JSON.stringify({ age: data.age, sex: data.sex, side: data.side })}\n` +
    `Demande du radiologue : ${prompt}`;

  // Pieces jointes eventuelles (image/PDF/texte) pour appuyer la generation.
  const content = [];
  const textDocs = [];
  (state.aiAttachments || []).forEach((a) => {
    if (a.kind === "pdf") content.push({ type: "document", source: { type: "base64", media_type: "application/pdf", data: a.data } });
    else if (a.kind === "image") content.push({ type: "image", source: { type: "base64", media_type: a.mediaType, data: a.data } });
    else if (a.kind === "text") textDocs.push(`--- Fichier "${a.name}" ---\n${a.text}`);
  });
  content.push({ type: "text", text: [textDocs.join("\n\n"), baseUserText].filter(Boolean).join("\n\n") });

  const MAX_ATTEMPTS = 3;
  const MAX_TOKENS = 4000;
  let lastError = null;

  for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
    $("aiStatus").textContent = attempt === 1
      ? `Generation IA (${model}) en cours...`
      : `Nouvelle tentative (${attempt}/${MAX_ATTEMPTS}) apres reponse incomplete...`;

    try {
      const messages = [{ role: "user", content }];
      if (attempt > 1 && lastError) {
        messages.push({
          role: "assistant",
          content: lastError.rawText ? lastError.rawText.slice(0, 2000) : "(reponse precedente invalide)"
        });
        messages.push({
          role: "user",
          content: "Ta reponse precedente etait tronquee ou n'etait pas un JSON valide. " +
            "Renvoie UNIQUEMENT le JSON complet {\"heading\":\"\",\"technique\":\"\",\"results\":[],\"conclusion\":\"\"}, " +
            "sans aucun texte autour, quitte a etre plus concis dans les champs pour tenir dans la limite de longueur."
        });
      }

      const res = await anthropicRequest({ model, max_tokens: MAX_TOKENS, system, messages });
      const text = (res?.content || []).filter((b) => b.type === "text").map((b) => b.text).join("\n");

      if (res?.stop_reason === "max_tokens") {
        // Troncature detectee AVANT meme d'essayer de parser : on retente
        // immediatement avec une consigne de concision plutot que d'echouer.
        lastError = new Error("reponse tronquee (max_tokens)");
        lastError.rawText = text;
        if (attempt < MAX_ATTEMPTS) continue;
      }

      const parsed = normalizeAiExam(extractJsonObject(text));
      if (!parsed.results.length && !parsed.conclusion) {
        throw new Error("le JSON recu ne contient ni resultats ni conclusion exploitables");
      }

      const exam = template(
        "ai-libre-" + Date.now().toString(36),
        selectedTemplate()?.title || "Compte rendu libre",
        "",
        parsed.technique || "",
        parsed.results || [],
        parsed.conclusion || "",
        parsed.heading || "COMPTE-RENDU D'EXAMEN RADIOLOGIQUE"
      );
      renderGeneratedExam(exam);
      state.aiAttachments = [];
      if ($("aiFiles")) $("aiFiles").textContent = "";
      $("aiStatus").textContent = attempt === 1
        ? "Compte rendu IA genere avec l'en-tete de l'hopital. A valider medicalement."
        : `Compte rendu IA genere (apres ${attempt} tentatives suite a une reponse incomplete). A valider medicalement.`;
      return; // succes : on sort de la boucle et de la fonction.

    } catch (error) {
      lastError = error;
      if (attempt >= MAX_ATTEMPTS) {
        const hint = /illisible|JSON|tronque/i.test(error.message)
          ? " Le modele a renvoye une reponse incomplete ou mal formee a plusieurs reprises : reessayez, reformulez plus court, ou passez temporairement sur Claude Opus 4.8 dans Parametres (plus lent mais plus fiable sur les demandes complexes)."
          : "";
        $("aiStatus").textContent = "Generation IA impossible : " + error.message + "." + hint;
        return;
      }
      // Erreur reseau/API (pas de JSON a corriger) : pas la peine de renvoyer
      // le contenu precedent au modele, on retente simplement l'appel initial.
      if (!/illisible|JSON|tronque/i.test(error.message)) {
        lastError = null;
      }
    }
  }
}

function renderGeneratedExam(model) {
  const hospital = selectedHospital();
  const data = patientData();
  const technique = model.technique ? `<h3>Technique</h3><p>${escapeHtml(model.technique)}</p>` : "";
  const closePaper = hospital.id === "nkoulou" ? "</td></tr></table>" : "";
  $("reportEditor").innerHTML = `
    <div class="cr-paper" style="font-family:${REPORT_FONT};font-size:${REPORT_SIZE};">
    ${renderHeader(hospital)}
    <h2>${escapeHtml(model.heading || model.title)}</h2>
    <h3>Identification</h3>
    <p><strong>Nom :</strong> ${escapeHtml(data.lastName)} &nbsp;&nbsp; <strong>Prénom :</strong> ${escapeHtml(data.firstName)} &nbsp;&nbsp; <strong>Âge :</strong> ${escapeHtml(data.age)} &nbsp;&nbsp; <strong>Sexe :</strong> ${escapeHtml(data.sex)}</p>
    <p><strong>Date :</strong> ${escapeHtml(data.date)} &nbsp;&nbsp; <strong>Médecin :</strong> ${escapeHtml(data.doctor)} &nbsp;&nbsp; <strong>N° :</strong> ${escapeHtml(data.record)}</p>
    ${technique}
    <h3>Résultats</h3>
    <ul>${(model.results || []).map((l) => `<li>${escapeHtml(l)}</li>`).join("")}</ul>
    <h3>Conclusion</h3>
    <p>${escapeHtml(model.conclusion || "")}</p>
    <p style="text-align:right; margin-top:42px;"><strong>${escapeHtml(data.radiologist)}</strong><br/>Radiologue</p>
    ${closePaper}
    </div>`;
  runQualityChecks();
}

async function importTemplateDocxFromSettings() {
  const file = $("templateDocxFile").files[0];
  if (!file) { $("templateImportStatus").textContent = "Choisissez d'abord un fichier .docx."; return; }
  $("templateImportStatus").textContent = "Lecture du document Word...";
  try {
    const text = await extractDocxText(file);
    let hospital = await buildHospitalFromTemplateText(text, $("templateHospitalName").value.trim() || file.name.replace(/\.docx$/i, ""));
    if (hasClaude()) {
      $("templateImportStatus").textContent = "Extraction intelligente des examens (Claude)...";
      hospital = await improveTemplateWithClaude(text, hospital).catch((e) => {
        $("templateImportStatus").textContent = "IA Claude indisponible (" + e.message + "). Structuration de base.";
        return hospital;
      });
    } else {
      const key = (localStorage.getItem("radassist.visionKey") || "").trim();
      if (key) {
        $("templateImportStatus").textContent = "Structuration IA du template (OpenAI)...";
        hospital = await improveTemplateWithAi(text, hospital, key).catch(() => hospital);
      }
    }
    const list = getCustomHospitals();
    list.push(hospital);
    saveCustomHospitals(list);
    loadHospitals(); hydrateHospitals();
    $("hospitalSelect").value = hospital.id; hydrateExams(); renderTemplates(); generateReport();
    $("templateImportStatus").textContent = `${hospital.name} importe avec ${hospital.exams.length} examen(s).`;
  } catch (error) {
    $("templateImportStatus").textContent = "Import impossible : " + error.message;
  }
}

async function extractDocxText(file) {
  const xml = await readZipTextEntry(file, "word/document.xml");
  const doc = new DOMParser().parseFromString(xml, "application/xml");
  const paragraphs = [...doc.getElementsByTagNameNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "p")]
    .map((p) => [...p.getElementsByTagNameNS("http://schemas.openxmlformats.org/wordprocessingml/2006/main", "t")].map((t) => t.textContent).join(""))
    .map((p) => p.replace(/\s+/g, " ").trim())
    .filter(Boolean);
  if (!paragraphs.length) throw new Error("aucun texte lisible dans le DOCX");
  return paragraphs.join("\n");
}

async function readZipTextEntry(file, entryName) {
  const buf = await file.arrayBuffer();
  const view = new DataView(buf);
  let eocd = -1;
  for (let i = buf.byteLength - 22; i >= 0; i -= 1) {
    if (view.getUint32(i, true) === 0x06054b50) { eocd = i; break; }
  }
  if (eocd < 0) throw new Error("fichier DOCX/ZIP invalide");
  const count = view.getUint16(eocd + 10, true);
  let ptr = view.getUint32(eocd + 16, true);
  const decoder = new TextDecoder();
  for (let i = 0; i < count; i += 1) {
    if (view.getUint32(ptr, true) !== 0x02014b50) throw new Error("index DOCX illisible");
    const method = view.getUint16(ptr + 10, true);
    const compSize = view.getUint32(ptr + 20, true);
    const nameLen = view.getUint16(ptr + 28, true);
    const extraLen = view.getUint16(ptr + 30, true);
    const commentLen = view.getUint16(ptr + 32, true);
    const localOffset = view.getUint32(ptr + 42, true);
    const name = decoder.decode(new Uint8Array(buf, ptr + 46, nameLen));
    if (name === entryName) {
      const localNameLen = view.getUint16(localOffset + 26, true);
      const localExtraLen = view.getUint16(localOffset + 28, true);
      const dataStart = localOffset + 30 + localNameLen + localExtraLen;
      const compressed = buf.slice(dataStart, dataStart + compSize);
      if (method === 0) return decoder.decode(new Uint8Array(compressed));
      if (method !== 8) throw new Error("compression DOCX non supportee");
      if (!("DecompressionStream" in window)) throw new Error("navigateur trop ancien pour lire les DOCX localement");
      const stream = new Blob([compressed]).stream().pipeThrough(new DecompressionStream("deflate-raw"));
      return decoder.decode(new Uint8Array(await new Response(stream).arrayBuffer()));
    }
    ptr += 46 + nameLen + extraLen + commentLen;
  }
  throw new Error("word/document.xml introuvable");
}

function buildHospitalFromTemplateText(text, name) {
  const lines = text.split("\n").map((s) => s.trim()).filter(Boolean);
  const headerLines = lines.slice(0, 8).filter((l) => /clinique|cabinet|hopital|hôpital|centre|poly/i.test(l)).slice(0, 3);
  const chunks = splitTemplateBlocks(lines);
  const exams = chunks.map((chunk, index) => examFromLines(chunk, index)).filter(Boolean).slice(0, 120);
  return {
    id: "h-docx-" + Date.now().toString(36),
    name,
    radiologist: "Dr E. NDONGO",
    header: { layout: "text", color: "#1F3864", logo: "", lines: headerLines.length ? headerLines : [name], sub: "" },
    exams: exams.length ? exams : [template("examen-importe", "Examen importe", "", "", lines.slice(0, 8), lines.slice(-1)[0] || "", "COMPTE-RENDU D'EXAMEN RADIOGRAPHIQUE")]
  };
}

function splitTemplateBlocks(lines) {
  const blocks = [];
  let current = [];
  const starts = /^(compte[\s-]*rendu|radiographie|echographie|échographie|scanner|irm|rx\b|radio\b)/i;
  lines.forEach((line) => {
    if (starts.test(line) && current.length > 6) { blocks.push(current); current = []; }
    current.push(line);
    if (/^conclusion\b/i.test(line) && current.length > 4) { blocks.push(current); current = []; }
  });
  if (current.length) blocks.push(current);
  return blocks.filter((b) => b.length > 2);
}

function examFromLines(lines, index) {
  const heading = lines.find((l) => /compte[\s-]*rendu/i.test(l)) || "COMPTE-RENDU D'EXAMEN RADIOGRAPHIQUE";
  const title = lines.find((l) => !/compte[\s-]*rendu|technique|resultats?|résultats?|conclusion/i.test(l) && l.length < 90) || `Examen importe ${index + 1}`;
  const techniqueIndex = lines.findIndex((l) => /^technique\b/i.test(l));
  const resultIndex = lines.findIndex((l) => /^(resultats?|résultats?)\b/i.test(l));
  const conclusionIndex = lines.findIndex((l) => /^conclusion\b/i.test(l));
  const technique = techniqueIndex >= 0 ? (lines[techniqueIndex + 1] || "") : "";
  const resultStart = resultIndex >= 0 ? resultIndex + 1 : Math.min(2, lines.length);
  const resultEnd = conclusionIndex > resultStart ? conclusionIndex : lines.length - 1;
  const results = lines.slice(resultStart, resultEnd).filter((l) => !/^(technique|resultats?|résultats?|conclusion)\b/i.test(l)).slice(0, 12);
  const conclusion = conclusionIndex >= 0 ? (lines[conclusionIndex + 1] || lines.slice(conclusionIndex + 1).join(" ")) : (lines[lines.length - 1] || "");
  return template(slugify(title) || `examen-${index + 1}`, title, /droit|gauche|bilateral|bilatéral/i.test(lines.join(" ")) ? "side" : "", technique, results, conclusion, heading);
}

async function improveTemplateWithClaude(text, fallbackHospital) {
  const system =
    "Tu analyses le TEXTE d'un document Word de comptes rendus radiologiques modeles (plusieurs examens types d'un hopital). " +
    "Ta tache : identifier CHAQUE examen distinct present dans le document et le structurer. " +
    "Un 'examen' est un modele de compte rendu identifiable (ex : Echographie abdominale, Radiographie du thorax, TDM cerebrale, Echographie pelvienne...). " +
    "Ignore le pur texte de mise en page, les en-tetes/pieds, les mentions administratives : ne cree un examen QUE pour un vrai modele de CR. " +
    "Pour chacun, extrais fidelement (sans rien inventer) : le titre court de l'examen, le titre du compte rendu (heading, en MAJUSCULES), la technique, " +
    "les lignes de resultats/descriptif (tableau de chaines), la conclusion, et si l'examen depend d'un cote D/G (requiresSide=\"side\" sinon \"\"). " +
    "Reponds UNIQUEMENT en JSON strict : " +
    "{\"name\":\"\",\"exams\":[{\"title\":\"\",\"heading\":\"\",\"requiresSide\":\"\",\"technique\":\"\",\"results\":[],\"conclusion\":\"\"}]}.";
  const raw = await claudeText(system, text.slice(0, 60000), 4000);
  const parsed = extractJsonObject(raw);
  if (parsed.name) fallbackHospital.name = parsed.name;
  if (Array.isArray(parsed.exams) && parsed.exams.length) {
    fallbackHospital.exams = parsed.exams.map((e, i) => template(
      slugify(e.title || ("exam-" + (i + 1))) || `exam-${i + 1}`,
      e.title || `Examen ${i + 1}`,
      e.requiresSide || "",
      e.technique || "",
      Array.isArray(e.results) ? e.results : (e.results ? [String(e.results)] : []),
      e.conclusion || "",
      e.heading || "COMPTE-RENDU D'EXAMEN RADIOLOGIQUE"
    ));
  }
  return fallbackHospital;
}

async function improveTemplateWithAi(text, fallbackHospital, key) {
  const body = {
    model: localStorage.getItem("radassist.visionModel") || "gpt-4o",
    temperature: 0,
    max_tokens: 3500,
    messages: [{
      role: "user",
      content: `Transforme ce texte de template Word radiologique en JSON strict RadAssist. Garde les examens, technique, resultats, conclusion. N'invente rien. Structure: {"name":"","header":{"lines":[]},"exams":[{"id":"","heading":"","title":"","requiresSide":"","technique":"","results":[],"conclusion":""}]}\n\n${text.slice(0, 28000)}`
    }]
  };
  const res = await fetch("https://api.openai.com/v1/chat/completions", {
    method: "POST",
    headers: { "Content-Type": "application/json", Authorization: `Bearer ${key}` },
    body: JSON.stringify(body)
  });
  if (!res.ok) throw new Error("HTTP " + res.status);
  const raw = (await res.json())?.choices?.[0]?.message?.content || "";
  const parsed = JSON.parse(raw.replace(/```json|```/g, "").trim());
  fallbackHospital.name = parsed.name || fallbackHospital.name;
  fallbackHospital.header.lines = parsed.header?.lines?.length ? parsed.header.lines : fallbackHospital.header.lines;
  if (Array.isArray(parsed.exams) && parsed.exams.length) {
    fallbackHospital.exams = parsed.exams.map((e, i) => template(slugify(e.id || e.title) || `ai-${i + 1}`, e.title || `Examen ${i + 1}`, e.requiresSide || "", e.technique || "", e.results || [], e.conclusion || "", e.heading || "COMPTE-RENDU D'EXAMEN RADIOGRAPHIQUE"));
  }
  return fallbackHospital;
}

function slugify(v) {
  return normalizeForMatch(v).replace(/\s+/g, "-").replace(/^-|-$/g, "");
}

function showModal(title, innerHTML, onSubmit) {
  const back = document.createElement("div");
  back.style.cssText = "position:fixed;inset:0;background:rgba(15,23,42,.45);display:grid;place-items:center;z-index:9999;padding:16px;";
  back.innerHTML = `
    <div style="background:#fff;border-radius:10px;max-width:560px;width:100%;max-height:90vh;overflow:auto;padding:20px;box-shadow:0 20px 50px rgba(0,0,0,.25);">
      <h2 style="margin:0 0 12px;font-size:18px;">${escapeHtml(title)}</h2>
      <div class="modal-body" style="display:grid;gap:10px;">${innerHTML}</div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
        <button class="btn ghost" data-act="cancel">Annuler</button>
        <button class="btn primary" data-act="ok">Enregistrer</button>
      </div>
    </div>`;
  document.body.appendChild(back);
  const close = () => { document.removeEventListener("keydown", onKey); back.remove(); };
  const onKey = (e) => { if (e.key === "Escape") close(); };
  document.addEventListener("keydown", onKey);
  back.querySelector('[data-act="cancel"]').onclick = close;
  back.addEventListener("click", (e) => { if (e.target === back) close(); });
  back.querySelector('[data-act="ok"]').onclick = async () => {
    const result = onSubmit(back);
    if (result && typeof result.then === "function") {
      const resolved = await result;
      if (resolved !== false) close();
      return;
    }
    if (result !== false) close();
  };
  // Focus du premier champ pour une saisie immediate.
  setTimeout(() => { back.querySelector("input, textarea, select")?.focus(); }, 0);
  return back;
}

function openHospitalModal() {
  const html = `
    <label>Nom de l'hopital <input id="mhName" placeholder="Ex: Clinique X" /></label>
    <label>Radiologue <input id="mhRadio" value="Dr E. NDONGO" /></label>
    <label>Ligne(s) d'en-tete (une par ligne) <textarea id="mhLines" rows="3" placeholder="CLINIQUE X&#10;Yaounde - Cameroun"></textarea></label>
    <label>Logo d'en-tete (image, optionnel) <input id="mhLogo" type="file" accept="image/*" /></label>
    <label>Template Word de l'hopital (optionnel) <input id="mhDocx" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" /></label>
    <small style="color:#64748b;">L'en-tete saisi ici sera repris exactement a l'impression.</small>`;
  showModal("Ajouter un hopital", html, async (root) => {
    const name = root.querySelector("#mhName").value.trim();
    if (!name) { root.querySelector("#mhName").focus(); return false; }
    const lines = root.querySelector("#mhLines").value.split("\n").map((s) => s.trim()).filter(Boolean);
    const radio = root.querySelector("#mhRadio").value.trim() || "Dr E. NDONGO";
    const file = root.querySelector("#mhLogo").files[0];
    const docx = root.querySelector("#mhDocx").files[0];
    const finish = async (logo) => {
      const id = "h-" + Date.now().toString(36);
      const hospital = { id, name, radiologist: radio, header: { layout: logo ? "logo-left" : "text", color: "#1F3864", logo: logo || "", lines: lines.length ? lines : [name], sub: "" }, exams: [] };
      if (docx) {
        const text = await extractDocxText(docx);
        const imported = buildHospitalFromTemplateText(text, name);
        hospital.exams = imported.exams;
        if (!lines.length) hospital.header.lines = imported.header.lines;
      }
      const list = getCustomHospitals(); list.push(hospital); saveCustomHospitals(list);
      loadHospitals(); hydrateHospitals(); $("hospitalSelect").value = id; hydrateExams(); renderTemplates(); generateReport();
    };
    if (file) {
      const logo = await new Promise((resolve) => { const r = new FileReader(); r.onload = () => resolve(String(r.result)); r.readAsDataURL(file); });
      await finish(logo);
    } else await finish("");
  });
}

function openExamModal() {
  const h = selectedHospital();
  if (!h) return;
  const html = `
    <p style="margin:0;color:#475569;">Hopital : <strong>${escapeHtml(h.name)}</strong></p>
    <label>Titre de l'examen <input id="meTitle" placeholder="Ex: Radiographie du Thorax" /></label>
    <label><input id="meSide" type="checkbox" style="width:auto;margin-right:6px;" />Examen avec cote (D/G)</label>
    <label>Technique <textarea id="meTech" rows="2"></textarea></label>
    <label>Resultats (une ligne par item) <textarea id="meRes" rows="5"></textarea></label>
    <label>Conclusion <textarea id="meConcl" rows="2"></textarea></label>`;
  showModal("Ajouter un examen", html, (root) => {
    const title = root.querySelector("#meTitle").value.trim();
    if (!title) { root.querySelector("#meTitle").focus(); return false; }
    const exam = {
      id: "e-" + Date.now().toString(36),
      heading: `Compte Rendu de ${title}`,
      title,
      requiresSide: root.querySelector("#meSide").checked ? "side" : "",
      technique: root.querySelector("#meTech").value.trim(),
      results: root.querySelector("#meRes").value.split("\n").map((s) => s.trim()).filter(Boolean),
      conclusion: root.querySelector("#meConcl").value.trim()
    };
    addExamToHospital(h.id, exam);
    loadHospitals(); hydrateExams(); $("examSelect").value = exam.id; renderTemplates(); generateReport();
  });
}

function addExamToHospital(hospitalId, exam) {
  const custom = getCustomHospitals();
  const ch = custom.find((h) => h.id === hospitalId);
  if (ch) { ch.exams = ch.exams || []; ch.exams.push(exam); saveCustomHospitals(custom); return; }
  const map = getCustomExams();
  map[hospitalId] = map[hospitalId] || [];
  map[hospitalId].push(exam);
  saveCustomExams(map);
}

function exportConfig() {
  const cfg = { customHospitals: getCustomHospitals(), customExams: getCustomExams() };
  downloadBlob(new Blob([JSON.stringify(cfg, null, 2)], { type: "application/json" }), "radassist-config.json");
}
function importConfig(file) {
  const r = new FileReader();
  r.onload = () => {
    try {
      const cfg = JSON.parse(r.result);
      if (Array.isArray(cfg)) { // tableau d'hopitaux
        const list = getCustomHospitals();
        cfg.forEach((h) => { if (h && h.id && !list.some((x) => x.id === h.id)) list.push(h); });
        saveCustomHospitals(list);
      } else {
        if (cfg.customHospitals) saveCustomHospitals(cfg.customHospitals);
        if (cfg.customExams) saveCustomExams(cfg.customExams);
      }
      loadHospitals(); hydrateHospitals(); renderTemplates(); generateReport();
      alert("Import termine.");
    } catch (e) { alert("Fichier invalide : " + e.message); }
  };
  r.readAsText(file);
}

/* ------------------------------------------------------------------ CONTROLES / RESEAU / PWA */
function runQualityChecks() {
  const data = patientData();
  const model = selectedTemplate();
  const checks = [
    check(Boolean(data.lastName), "Nom du patient renseigne", "Le nom du patient manque."),
    check(Boolean(data.age), "Age renseigne", "L'age manque."),
    check(Boolean(data.record), "Numero renseigne", "Le numero du bulletin manque."),
    check(!(model?.requiresSide) || Boolean(data.side), "Cote precise si necessaire", "Le cote doit etre choisi pour cet examen."),
    check(Boolean($("reportEditor").textContent.trim()), "Compte rendu genere", "Le compte rendu n'est pas encore genere."),
    check(navigator.onLine, "Connexion disponible", "Mode hors ligne: les donnees restent locales.")
  ];
  $("qualityChecks").innerHTML = checks.map((i) => `
    <div class="check ${i.ok ? "good" : "warn"}"><strong>${i.ok ? "OK" : "A verifier"}</strong><p>${escapeHtml(i.ok ? i.good : i.bad)}</p></div>`).join("");
}
function check(ok, good, bad) { return { ok, good, bad }; }

function updateNetworkStatus() {
  const online = navigator.onLine;
  $("networkDot").className = `status-dot ${online ? "online" : "offline"}`;
  $("networkLabel").textContent = online ? "Connecte" : "Hors ligne";
  runQualityChecks();
}
function setupPwa() {
  window.addEventListener("beforeinstallprompt", (e) => { e.preventDefault(); state.deferredPrompt = e; $("installBtn").hidden = false; });
  $("installBtn").addEventListener("click", async () => {
    if (!state.deferredPrompt) return;
    state.deferredPrompt.prompt(); await state.deferredPrompt.userChoice;
    state.deferredPrompt = null; $("installBtn").hidden = true;
  });
}
function registerServiceWorker() {
  if ("serviceWorker" in navigator) navigator.serviceWorker.register("sw.js").catch(() => {});
}
function escapeHtml(v) {
  return String(v ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
}

init();
