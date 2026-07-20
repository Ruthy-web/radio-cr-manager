/**
 * Moteur d'insertion sémantique (F5) — portage fidèle de
 * `App\Services\SemanticInsertionService` (PHP), lui-même porté de
 * `semanticInsert`/`matchScore`/`TERM_SYNONYMS` (frontend-existant/app.js).
 * Fonctionne entièrement hors ligne (< 5 ms), sans appel réseau : remplace
 * la ligne de résultat qui parle de la même structure anatomique plutôt que
 * d'ajouter un doublon, avec bonus/malus de latéralité et réécriture de la
 * conclusion si une anomalie est dictée alors qu'elle dit encore « normal ».
 */

const TERM_SYNONYMS = [
  ['battement cardiaque', 'frequence cardiaque', 'rythme cardiaque', 'bpm', 'activite cardiaque', 'bdc', 'silhouette cardiaque', 'index cardio thoracique'],
  ['saphene', 'grande veine saphene', 'petite veine saphene', 'crosse saphene'],
  ['vesicule', 'vesicule biliaire', 'vb'],
  ['voie biliaire', 'choledoque', 'vbp', 'voies biliaires'],
  ['rein', 'reins', 'renal', 'renale', 'pyelocalicielle', 'pyelocaliciel'],
  ['foie', 'hepatique', 'hepatomegalie', 'fleche hepatique', 'parenchyme hepatique'],
  ['rate', 'splenique', 'splenomegalie'],
  ['pancreas', 'pancreatique', 'wirsung'],
  ['uterus', 'uterin', 'uterine', 'myometre'],
  ['endometre', 'endometriale', 'ligne endometriale'],
  ['ovaire', 'ovarien', 'ovarienne', 'annexe', 'annexiel'],
  ['prostate', 'prostatique'],
  ['vessie', 'vesical', 'vesicale'],
  ['thyroide', 'thyroidien', 'thyroidienne', 'lobe thyroidien'],
  ['plevre', 'pleural', 'epanchement pleural', 'culs de sac costodiaphragmatiques', 'cul de sac'],
  ['poumon', 'pulmonaire', 'transparence pulmonaire', 'parenchyme pulmonaire', 'opacite', 'alveolaire', 'condensation', 'foyer', 'lobe', 'hyperclarte', 'pneumothorax', 'bulle', 'nodule pulmonaire'],
  ['mediastin', 'mediastinal'],
  ['coupole', 'diaphragme', 'diaphragmatique', 'coupoles diaphragmatiques'],
  ['aorte', 'aortique'],
  ['veine cave', 'vci'],
  ['carotide', 'carotidien'],
  ['femorale', 'femoral', 'veine femorale', 'artere femorale'],
  ['poplitee', 'poplite'],
  ['tibial', 'tibiale', 'tibia'],
  ['fibula', 'perone', 'peroneal'],
  ['femur', 'femoral'],
  ['humerus', 'humeral', 'humerale'],
  ['interligne', 'interlignes', 'pincement articulaire'],
  ['epanchement', 'liquide libre', 'lame liquidienne', 'douglas'],
  ['ganglion', 'adenopathie', 'adenomegalie', 'ganglionnaire'],
  ['placenta', 'placentaire'],
  ['liquide amniotique', 'grande citerne', 'ila'],
  ['col uterin', 'col', 'cervical'],
  ['tendon', 'tendineux', 'coiffe des rotateurs', 'supra epineux', 'sus epineux'],
  ['sinus', 'sinusien', 'maxillaire', 'frontal', 'ethmoidal', 'sphenoidal'],
  ['testicule', 'testiculaire', 'scrotal', 'epididyme'],
  ['sein', 'mammaire', 'glande mammaire'],
  ['fracture', 'trait de fracture', 'lyse', 'corticale'],
  ['thrombose', 'thrombus', 'obstrue', 'obstruee', 'occlusion', 'incompressible', 'compressibilite'],
];

const PATHOLOGY_WORDS = /\b(obstru|thrombos|thrombus|fractur|lesion|nodul|masse|tumeur|kyste|dilat|epanchement|stenos|anevrysm|adenopath|metastas|hydronephros|lithias|calcul|augment|hypertroph|epaissi|infiltrat|opacit|hyperclart|luxation|tassement|hernie|pincement|oedem|abces|collection|incompressib|reflux|insuffisan|anormal|suspect|hypoecho|hyperecho|heterogen)\w*/i;

const MATCH_STOP = new Set(['le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'au', 'aux', 'en', 'pas', 'est', 'sans', 'avec', 'sur', 'dans', 'ni', 'ou', 'son', 'ses', 'leur', 'que', 'qui']);

function normalizeSem(v) {
  return String(v || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9,;.=]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function statementTokens(s) {
  return normalizeSem(s)
    .replace(/[,;.=]/g, ' ')
    .split(' ')
    .filter((w) => w.length > 2 && !MATCH_STOP.has(w));
}

function expandWithSynonyms(tokens) {
  const set = new Set(tokens);
  const joined = ` ${tokens.join(' ')} `;

  for (const group of TERM_SYNONYMS) {
    const triggered = group.some(
      (g) => joined.includes(` ${normalizeSem(g)} `) || normalizeSem(g).split(' ').some((w) => tokens.includes(w))
    );

    if (!triggered) continue;

    group.forEach((g) =>
      normalizeSem(g)
        .split(' ')
        .forEach((w) => {
          if (w.length > 2) set.add(w);
        })
    );
  }

  return set;
}

function matchScore(stmtTokens, lineText) {
  const lineTokens = new Set(statementTokens(lineText));
  if (!lineTokens.size) return 0;

  const expanded = expandWithSynonyms([...stmtTokens]);
  let hits = 0;
  for (const t of lineTokens) if (expanded.has(t)) hits++;

  const expandedJoined = [...expanded].join(' ');
  const sSide = /gauche/.test(expandedJoined) ? 'g' : /droit/.test(expandedJoined) ? 'd' : '';
  const lSide = /gauche/.test(normalizeSem(lineText)) ? 'g' : /droit/.test(normalizeSem(lineText)) ? 'd' : '';

  let bonus = 0;
  if (sSide && lSide) bonus += sSide === lSide ? 1.5 : -2;

  return hits + bonus;
}

function splitStatements(dictation) {
  return dictation
    .split(/(?<=[.;])\s+|\n+|\bensuite\b|\bpar ailleurs\b|\bde plus\b/i)
    .map((s) => s.replace(/^[,;.\s]+|[,;\s]+$/g, '').trim())
    .filter((s) => s.length > 3);
}

function polishStatement(s) {
  let t = s.trim().replace(/\s+/g, ' ');
  t = t
    .replace(/\s*=\s*/g, ' : ')
    .replace(/(\d)\s*bpm/gi, '$1 bpm')
    .replace(/(\d)\s*mm/gi, '$1 mm')
    .replace(/(\d)\s*cm/gi, '$1 cm');
  t = t.charAt(0).toUpperCase() + t.slice(1);
  if (!/[.]$/.test(t)) t += '.';
  return t;
}

/**
 * @param {string} dictation
 * @param {Array<{text: string, abnormal?: boolean, heading?: boolean}>} results
 * @param {string|null} conclusion
 * @returns {{results: Array, conclusion: string|null, replaced: number, added: number}}
 */
export function semanticInsert(dictation, results, conclusion) {
  const out = results.map((r) => ({ text: r.text, abnormal: !!r.abnormal, heading: !!r.heading }));
  const originalConclusion = conclusion;
  const anomalies = [];
  let replaced = 0;
  let added = 0;

  for (const stmt of splitStatements(dictation)) {
    const mConclusion = stmt.match(/^conclusions?\s*:?\s*(.+)$/i);
    if (mConclusion) {
      conclusion = polishStatement(mConclusion[1]);
      continue;
    }

    const tokens = statementTokens(stmt);
    if (!tokens.length) continue;

    let bestIndex = null;
    let bestScore = 0;

    out.forEach((result, index) => {
      if (result.heading) return;
      const score = matchScore(tokens, result.text);
      if (score > bestScore) {
        bestScore = score;
        bestIndex = index;
      }
    });

    const isPathology = PATHOLOGY_WORDS.test(stmt);

    if (bestIndex !== null && bestScore >= 2) {
      const currentText = out[bestIndex].text;
      const mValue = stmt.match(/^(.{3,60}?)[:=]\s*([\d.,]+\s*(bpm|mm|cm|ml|g\/l|ui\/l|%|sa)?)\s*$/i);

      if (mValue && /\d/.test(currentText)) {
        out[bestIndex].text = currentText.replace(/[\d.,]+\s*(bpm|mm|cm|ml|%|sa)?/i, mValue[2].trim());
      } else {
        out[bestIndex].text = polishStatement(stmt);
      }

      out[bestIndex].abnormal = isPathology;
      replaced++;
    } else {
      out.push({ text: polishStatement(stmt), abnormal: isPathology, heading: false });
      added++;
    }

    if (isPathology) anomalies.push(polishStatement(stmt).replace(/\.$/, ''));
  }

  if (anomalies.length && originalConclusion && /normal/i.test(originalConclusion)) {
    conclusion = `${anomalies.join('. ')}. Le reste de l'examen est sans particularité.`;
  }

  return { results: out, conclusion, replaced, added };
}
