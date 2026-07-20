<?php

namespace App\Services;

/**
 * Portage fidèle du moteur d'insertion sémantique de la PWA existante
 * (frontend-existant/app.js : semanticInsert, matchScore, TERM_SYNONYMS).
 * Mêmes règles exactes : score de correspondance par jetons + synonymes,
 * bonus/malus de latéralité gauche/droite, remplacement ciblé des valeurs
 * numériques, réécriture de la conclusion si une anomalie est dictée alors
 * que la conclusion dit encore « normal » (F5).
 *
 * Contrairement à la version JS qui manipule le DOM (<li> du compte rendu
 * affiché), ce service opère sur le tableau `results` du JSON `content`
 * d'un Report ([{text, abnormal, heading}, ...]) : les lignes marquées
 * `heading` (sous-titres d'organe, ex. « Foie ») ne sont jamais candidates
 * au remplacement, exactement comme elles ne seraient jamais un <li> de
 * constatation dans l'app d'origine.
 */
class SemanticInsertionService
{
    /**
     * ~40 groupes de synonymes radiologiques — copie exacte de la référence.
     *
     * @var array<int, array<int, string>>
     */
    private const TERM_SYNONYMS = [
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

    private const PATHOLOGY_WORDS = '/\b(obstru|thrombos|thrombus|fractur|lesion|nodul|masse|tumeur|kyste|dilat|epanchement|stenos|anevrysm|adenopath|metastas|hydronephros|lithias|calcul|augment|hypertroph|epaissi|infiltrat|opacit|hyperclart|luxation|tassement|hernie|pincement|oedem|abces|collection|incompressib|reflux|insuffisan|anormal|suspect|hypoecho|hyperecho|heterogen)\w*/i';

    /** @var array<int, string> */
    private const MATCH_STOP = ['le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'au', 'aux', 'en', 'pas', 'est', 'sans', 'avec', 'sur', 'dans', 'ni', 'ou', 'son', 'ses', 'leur', 'que', 'qui'];

    /**
     * Insère une dictée dans les résultats/conclusion d'un compte rendu,
     * en remplaçant la ligne qui parle de la même structure anatomique
     * plutôt que d'ajouter un doublon (hors ligne, < 5 ms — F5).
     *
     * @param  array<int, array{text: string, abnormal: bool, heading?: bool}>  $results
     * @return array{results: array<int, array{text: string, abnormal: bool, heading: bool}>, conclusion: ?string, replaced: int, added: int}
     */
    public function insert(string $dictation, array $results, ?string $conclusion): array
    {
        $results = array_map(fn (array $r) => [
            'text' => $r['text'],
            'abnormal' => $r['abnormal'] ?? false,
            'heading' => $r['heading'] ?? false,
        ], $results);

        $originalConclusion = $conclusion;
        $anomalies = [];
        $replaced = 0;
        $added = 0;

        foreach ($this->splitStatements($dictation) as $stmt) {
            // La phrase "conclusion : ..." va directement dans la conclusion.
            if (preg_match('/^conclusions?\s*:?\s*(.+)$/iu', $stmt, $mConclusion) === 1) {
                $conclusion = $this->polishStatement($mConclusion[1]);

                continue;
            }

            $tokens = $this->statementTokens($stmt);

            if ($tokens === []) {
                continue;
            }

            $bestIndex = null;
            $bestScore = 0;

            foreach ($results as $index => $result) {
                if ($result['heading']) {
                    continue;
                }

                $score = $this->matchScore($tokens, $result['text']);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $index;
                }
            }

            $isPathology = preg_match(self::PATHOLOGY_WORDS, $stmt) === 1;

            if ($bestIndex !== null && $bestScore >= 2) {
                $currentText = $results[$bestIndex]['text'];

                // Cas "paramètre : valeur" -> on ne remplace que la valeur numérique dans la ligne.
                if (
                    preg_match('/^(.{3,60}?)[:=]\s*([\d.,]+\s*(bpm|mm|cm|ml|g\/l|ui\/l|%|sa)?)\s*$/iu', $stmt, $mValue) === 1
                    && preg_match('/\d/', $currentText) === 1
                ) {
                    $replacementValue = trim($mValue[2]);
                    $results[$bestIndex]['text'] = preg_replace_callback(
                        '/[\d.,]+\s*(bpm|mm|cm|ml|%|sa)?/iu',
                        fn () => $replacementValue,
                        $currentText,
                        1
                    );
                } else {
                    $results[$bestIndex]['text'] = $this->polishStatement($stmt);
                }

                $results[$bestIndex]['abnormal'] = $isPathology;
                $replaced++;
            } else {
                $results[] = [
                    'text' => $this->polishStatement($stmt),
                    'abnormal' => $isPathology,
                    'heading' => false,
                ];
                $added++;
            }

            if ($isPathology) {
                $anomalies[] = rtrim($this->polishStatement($stmt), '.');
            }
        }

        // Conclusion heuristique : des anomalies dictées + une conclusion encore
        // "normale" (au moment de l'appel) -> on la réécrit.
        if ($anomalies !== [] && $originalConclusion !== null && preg_match('/normal/iu', $originalConclusion) === 1) {
            $conclusion = implode('. ', $anomalies).'. Le reste de l\'examen est sans particularité.';
        }

        return [
            'results' => $results,
            'conclusion' => $conclusion,
            'replaced' => $replaced,
            'added' => $added,
        ];
    }

    /**
     * Score de correspondance entre un énoncé dicté (jetons) et une ligne du template.
     *
     * @param  array<int, string>  $stmtTokens
     */
    public function matchScore(array $stmtTokens, string $lineText): float
    {
        // Dédupliqué comme le Set JS d'origine : un mot répété dans la ligne
        // ne doit pas être compté plusieurs fois.
        $lineTokens = array_unique($this->statementTokens($lineText));

        if ($lineTokens === []) {
            return 0;
        }

        $expanded = $this->expandWithSynonyms($stmtTokens);
        $hits = 0;

        foreach ($lineTokens as $token) {
            if (isset($expanded[$token])) {
                $hits++;
            }
        }

        $expandedJoined = implode(' ', array_keys($expanded));
        $statementSide = str_contains($expandedJoined, 'gauche') ? 'g' : (str_contains($expandedJoined, 'droit') ? 'd' : '');
        $normalizedLine = $this->normalizeSem($lineText);
        $lineSide = str_contains($normalizedLine, 'gauche') ? 'g' : (str_contains($normalizedLine, 'droit') ? 'd' : '');

        $bonus = 0;

        if ($statementSide !== '' && $lineSide !== '') {
            $bonus += $statementSide === $lineSide ? 1.5 : -2;
        }

        return $hits + $bonus;
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array<string, true> jetons étendus, utilisé comme ensemble (clés)
     */
    private function expandWithSynonyms(array $tokens): array
    {
        $set = array_fill_keys($tokens, true);
        $joined = ' '.implode(' ', $tokens).' ';

        foreach (self::TERM_SYNONYMS as $group) {
            $triggered = false;

            foreach ($group as $member) {
                $normalizedMember = $this->normalizeSem($member);

                if (str_contains($joined, ' '.$normalizedMember.' ')) {
                    $triggered = true;

                    break;
                }

                foreach (explode(' ', $normalizedMember) as $word) {
                    if (in_array($word, $tokens, true)) {
                        $triggered = true;

                        break 2;
                    }
                }
            }

            if (! $triggered) {
                continue;
            }

            foreach ($group as $member) {
                foreach (explode(' ', $this->normalizeSem($member)) as $word) {
                    if (mb_strlen($word) > 2) {
                        $set[$word] = true;
                    }
                }
            }
        }

        return $set;
    }

    /**
     * @return array<int, string>
     */
    private function statementTokens(string $s): array
    {
        $normalized = preg_replace('/[,;.=]/u', ' ', $this->normalizeSem($s)) ?? '';
        $words = array_filter(explode(' ', $normalized), fn ($w) => $w !== '');

        return array_values(array_filter(
            $words,
            fn (string $w) => mb_strlen($w) > 2 && ! in_array($w, self::MATCH_STOP, true)
        ));
    }

    private function normalizeSem(string $v): string
    {
        $v = mb_strtolower($v);
        $v = $this->stripDiacritics($v);
        $v = preg_replace('/[^a-z0-9,;.=]/u', ' ', $v) ?? '';
        $v = preg_replace('/\s+/u', ' ', $v) ?? '';

        return trim($v);
    }

    private function stripDiacritics(string $v): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);

        return $transliterated !== false ? $transliterated : $v;
    }

    /**
     * @return array<int, string>
     */
    private function splitStatements(string $dictation): array
    {
        $parts = preg_split(
            '/(?<=[.;])\s+|\n+|\bensuite\b|\bpar ailleurs\b|\bde plus\b/iu',
            $dictation
        ) ?: [];

        $parts = array_map(function (string $s) {
            $s = preg_replace('/^[,;.\s]+|[,;\s]+$/u', '', $s) ?? $s;

            return trim($s);
        }, $parts);

        return array_values(array_filter($parts, fn (string $s) => mb_strlen($s) > 3));
    }

    private function polishStatement(string $s): string
    {
        $t = trim($s);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        $t = preg_replace('/\s*=\s*/u', ' : ', $t) ?? $t;
        $t = preg_replace('/(\d)\s*bpm/iu', '$1 bpm', $t) ?? $t;
        $t = preg_replace('/(\d)\s*mm/iu', '$1 mm', $t) ?? $t;
        $t = preg_replace('/(\d)\s*cm/iu', '$1 cm', $t) ?? $t;

        $firstChar = mb_substr($t, 0, 1);
        $t = mb_strtoupper($firstChar).mb_substr($t, 1);

        if (! str_ends_with($t, '.')) {
            $t .= '.';
        }

        return $t;
    }
}
