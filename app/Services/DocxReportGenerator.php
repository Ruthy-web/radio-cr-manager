<?php

namespace App\Services;

use App\Models\ExamTemplate;
use App\Models\Report;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Génère le DOCX final d'un compte rendu par modification du XML du
 * template institutionnel d'origine — jamais par reconstruction (R1).
 *
 * Principe : le DOCX source de l'hôpital contient TOUS ses examens, un par
 * page. On repère le bloc de nœuds XML correspondant à l'examen du compte
 * rendu (mêmes règles de détection que HospitalDocxParser), on supprime les
 * autres blocs, puis on substitue uniquement le TEXTE des identités, des
 * résultats et de la conclusion dans les nœuds conservés — entête, logo,
 * polices, couleurs et absence de soulignement des titres restent
 * strictement ceux du fichier d'origine.
 */
class DocxReportGenerator
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const GENERIC_TITLE_RE = '/^compte[\s-]*rendu\s+d[\'’]imagerie\s+m[ée]dicale/iu';

    private const TITLE_RE = '/^compte[\s-]*rendu\b/iu';

    private const IDENTIFICATION_RE = '/^(identification|identit[ée])\b/iu';

    private const SIGNATURE_RE = '/^(dr\.?\s|docteur\s|pr\.?\s|professeur\s|radiologue$|m[ée]decin radiologue|fait\s+à\s|agr[ée]g[ée]e?\s)/iu';

    /**
     * Intitulés de section reconnus. Certains templates (Nkoulou) portent le
     * contenu directement sur la même ligne que l'intitulé (« Conclusion :
     * Radiographie normale. ») ; le groupe capturant récupère alors ce
     * contenu en ligne pour l'utiliser comme paragraphe de section lui-même.
     */
    private const SECTION_HEADINGS = [
        'technique' => '/^techniques?\s*:?\s*$|^techniques?\s*:\s*(?<inline>.+)$/iu',
        'results' => '/^(r[ée]sultats?|descriptif|description)\s*:?\s*$/iu',
        'conclusion' => '/^conclusion\s*:?\s*$|^conclusion\s*:\s*(?<inline>.+)$/iu',
    ];

    private const ANOMALY_COLOR = 'C00000';

    /**
     * @return string chemin absolu du DOCX généré (storage/app/private/reports/{id}.docx)
     */
    public function generate(Report $report): string
    {
        $hospital = $report->hospital;
        $examTemplate = $report->examTemplate;

        if (! $examTemplate) {
            throw new RuntimeException(
                "Génération impossible : ce compte rendu n'est rattaché à aucun examen du catalogue."
            );
        }

        if (! $hospital->header_docx_path) {
            throw new RuntimeException("Génération impossible : l'hôpital « {$hospital->name} » n'a pas de template DOCX.");
        }

        $sourcePath = storage_path('app/'.$hospital->header_docx_path);

        if (! is_file($sourcePath)) {
            throw new RuntimeException("Template introuvable : {$sourcePath}");
        }

        $outputPath = storage_path("app/private/reports/{$report->id}.docx");
        File::ensureDirectoryExists(dirname($outputPath));
        File::copy($sourcePath, $outputPath);

        $zip = new ZipArchive;

        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException("Impossible d'ouvrir le DOCX généré : {$outputPath}");
        }

        $documentXml = $zip->getFromName('word/document.xml');

        if ($documentXml === false) {
            $zip->close();
            throw new RuntimeException('word/document.xml introuvable dans le template.');
        }

        $dom = new DOMDocument;
        $dom->loadXML($documentXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS);

        $this->keepOnlyTargetExam($dom, $xpath, $examTemplate);
        $this->injectContent($dom, $xpath, $report, $examTemplate);

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $dom->saveXML());
        $zip->close();

        return $outputPath;
    }

    /**
     * Supprime du corps du document tous les blocs d'examen sauf celui
     * demandé, qu'il soit porté par des paragraphes (Nkoulou, HMR1, Zalom,
     * CHRACERH) ou par une ligne de tableau (CHM).
     */
    private function keepOnlyTargetExam(DOMDocument $dom, DOMXPath $xpath, ExamTemplate $examTemplate): void
    {
        $body = $xpath->query('/w:document/w:body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new RuntimeException('Structure DOCX invalide : <w:body> introuvable.');
        }

        if ($this->tryKeepParagraphBlock($dom, $body, $xpath, $examTemplate)) {
            return;
        }

        if ($this->tryKeepTableRow($body, $xpath, $examTemplate)) {
            return;
        }

        throw new RuntimeException(
            "Impossible de localiser l'examen « {$examTemplate->title} » dans le template source."
        );
    }

    private function tryKeepParagraphBlock(DOMDocument $dom, DOMElement $body, DOMXPath $xpath, ExamTemplate $examTemplate): bool
    {
        /** @var array<int, DOMElement> $topLevel */
        $topLevel = [];

        foreach ($body->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $topLevel[] = $child;
            }
        }

        // Repère les paragraphes de titre d'examen, puis étend chaque bloc
        // vers l'ARRIÈRE pour absorber les nœuds qui lui appartiennent
        // visuellement mais le précèdent dans le XML : tableau d'entête
        // répété (ex. Zalom) et/ou paragraphe vide de saut de page. Un tel
        // nœud est rattaché au bloc SUIVANT, jamais laissé en fin du bloc
        // précédent (sinon il migre avec le mauvais examen, ou finit sur
        // une page finale vierge).
        $titleIndexes = [];

        foreach ($topLevel as $index => $node) {
            $text = $this->nodeText($node);

            if ($text !== '' && (
                preg_match(self::GENERIC_TITLE_RE, $text) === 1
                || preg_match(self::TITLE_RE, $text) === 1
            )) {
                $titleIndexes[] = $index;
            }
        }

        if ($titleIndexes === []) {
            return false;
        }

        $blockStarts = [];

        foreach ($titleIndexes as $titleIndex) {
            $start = $titleIndex;

            while ($start > 0) {
                $prev = $topLevel[$start - 1];
                $isAbsorbable = $prev->localName === 'tbl' || ($prev->localName === 'p' && $this->nodeText($prev) === '');

                if (! $isAbsorbable || in_array($start - 1, $titleIndexes, true)) {
                    break;
                }

                $start--;
            }

            $blockStarts[] = $start;
        }

        $blocks = [];

        foreach ($blockStarts as $i => $start) {
            $end = $blockStarts[$i + 1] ?? (count($topLevel));
            $nodes = array_slice($topLevel, $start, $end - $start);
            $blocks[] = ['nodes' => $nodes, 'text' => implode(' ', array_map(fn ($n) => $this->nodeText($n), $nodes))];
        }

        $target = $this->findBestBlock($blocks, $examTemplate);

        if ($target === null) {
            return false;
        }

        $keepNodes = $target['nodes'];

        // Un paragraphe vide en tête de bloc (souvent porteur du saut de
        // page qui amenait CET examen en début de page dans le document
        // source) est désormais inutile : gardé, il provoquerait une page
        // de garde vierge avant le contenu.
        while (count($keepNodes) > 1) {
            $first = $keepNodes[0];

            if ($first->localName === 'p' && $this->nodeText($first) === '') {
                array_shift($keepNodes);

                continue;
            }

            break;
        }

        // Filet de sécurité pour le dernier examen du document (rien à
        // rattacher à un bloc suivant) : purge les paragraphes vides en fin
        // de bloc pour éviter une page finale vierge.
        while (count($keepNodes) > 1) {
            $last = $keepNodes[count($keepNodes) - 1];

            if ($last->localName === 'p' && $this->nodeText($last) === '') {
                array_pop($keepNodes);

                continue;
            }

            break;
        }

        $sectPr = $xpath->query('w:sectPr', $body)->item(0);

        foreach ($topLevel as $node) {
            if (! in_array($node, $keepNodes, true) && $node !== $sectPr) {
                $body->removeChild($node);
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{nodes: array<int, DOMElement>, text: string}>  $blocks
     * @return array{nodes: array<int, DOMElement>, text: string}|null
     */
    private function findBestBlock(array $blocks, ExamTemplate $examTemplate): ?array
    {
        $normalizedTarget = $this->normalize($examTemplate->title);
        $normalizedHeading = $this->normalize($examTemplate->heading);

        foreach ($blocks as $block) {
            // Recherche sur tout le bloc (pas seulement son début) : un
            // tableau d'entête répété absorbé en tête de bloc (ex. Zalom)
            // peut à lui seul dépasser plusieurs centaines de caractères.
            $normalizedBlockStart = $this->normalize(trim($block['text']));

            if (
                str_contains($normalizedBlockStart, $normalizedTarget)
                || str_contains($normalizedBlockStart, $normalizedHeading)
            ) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Templates génériques type CHM : tous les examens sont des lignes d'un
     * même tableau, le vrai nom de l'examen étant porté par un champ « Examen : ».
     */
    private function tryKeepTableRow(DOMElement $body, DOMXPath $xpath, ExamTemplate $examTemplate): bool
    {
        $normalizedTarget = $this->normalize($examTemplate->title);

        foreach ($xpath->query('.//w:tbl', $body) as $table) {
            /** @var DOMElement $table */
            $rows = $xpath->query('w:tr', $table);
            $matchedRow = null;

            foreach ($rows as $row) {
                /** @var DOMElement $row */
                // normalize() réduit toute ponctuation (dont « : ») à un espace :
                // on recherche donc « examen <titre normalisé> » sans deux-points littéral.
                $rowText = $this->normalize($this->nodeText($row));

                if (str_contains($rowText, 'examen '.$normalizedTarget)) {
                    $matchedRow = $row;

                    break;
                }
            }

            if ($matchedRow === null) {
                continue;
            }

            foreach (iterator_to_array($rows) as $row) {
                if ($row !== $matchedRow) {
                    $table->removeChild($row);
                }
            }

            return true;
        }

        return false;
    }

    private function injectContent(DOMDocument $dom, DOMXPath $xpath, Report $report, ExamTemplate $examTemplate): void
    {
        $content = $report->content ?? [];
        $paragraphs = $xpath->query('//w:p');
        $section = null; // null|identification|technique|results|conclusion

        $resultParagraphs = [];
        $techniqueParagraphs = [];
        $conclusionParagraphs = [];

        foreach ($paragraphs as $paragraph) {
            /** @var DOMElement $paragraph */
            $text = trim($this->nodeText($paragraph));

            if ($text === '') {
                continue;
            }

            $isExamTitle = preg_match(self::GENERIC_TITLE_RE, $text) === 1 || preg_match(self::TITLE_RE, $text) === 1;

            if ($isExamTitle) {
                // R1 : les titres et sous-titres ne sont jamais soulignés, même si
                // le template source porte encore un <w:u/> hérité (mise en forme
                // uniquement retirée ici, tout le reste du run est conservé).
                $this->stripUnderline($paragraph);

                continue;
            }

            if (preg_match(self::IDENTIFICATION_RE, $text) === 1) {
                $section = 'identification';
                $this->stripUnderline($paragraph);

                continue;
            }

            // La signature (« Dr X », « Radiologue », « Fait à ... ») termine
            // la conclusion : sans ce garde-fou, ces lignes seraient prises
            // pour des paragraphes de conclusion supplémentaires et écrasées.
            if ($section === 'conclusion' && preg_match(self::SIGNATURE_RE, $text) === 1) {
                $section = null;

                continue;
            }

            $heading = $this->matchSectionHeading($text);

            if ($heading !== null) {
                [$section, $inline] = $heading;

                // Intitulé et contenu sur la même ligne (ex. Nkoulou) : ce
                // paragraphe EST déjà le paragraphe de contenu de la section ;
                // le remplacement de texte qui suivra repart du dernier run
                // (la valeur), donc pas besoin de retirer le soulignement ici.
                if ($inline !== null) {
                    if ($section === 'technique') {
                        $techniqueParagraphs[] = $paragraph;
                    } elseif ($section === 'conclusion') {
                        $conclusionParagraphs[] = $paragraph;
                    }
                } else {
                    $this->stripUnderline($paragraph);
                }

                continue;
            }

            if ($section === 'identification') {
                $this->injectIdentity($paragraph, $text, $report);

                continue;
            }

            if ($section === 'technique') {
                $techniqueParagraphs[] = $paragraph;

                continue;
            }

            if ($section === 'results' && $this->hasBulletRun($paragraph)) {
                $resultParagraphs[] = $paragraph;

                continue;
            }

            if ($section === 'conclusion') {
                $conclusionParagraphs[] = $paragraph;
            }
        }

        $this->replaceResults($dom, $resultParagraphs, $content['results'] ?? []);
        $this->replaceSingleTextSection($techniqueParagraphs, $content['technique'] ?? null);
        $this->replaceSingleTextSection($conclusionParagraphs, $content['conclusion'] ?? null);
    }

    /**
     * @return array{0: string, 1: ?string}|null [section, contenu en ligne éventuel]
     */
    private function matchSectionHeading(string $text): ?array
    {
        foreach (self::SECTION_HEADINGS as $section => $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return [$section, $m['inline'] ?? null];
            }
        }

        return null;
    }

    /**
     * Certains templates portent plusieurs libellés d'identité sur une même
     * ligne, séparés par des tabulations (ex. « Sexe :Âge : », « N° Dossier
     * :Date d'examen : »). On repère donc TOUS les segments « Libellé : »
     * du paragraphe et on insère chaque valeur juste après son libellé,
     * plutôt que de supposer un seul champ par paragraphe.
     */
    private function injectIdentity(DOMElement $paragraph, string $text, Report $report): void
    {
        $newText = preg_replace_callback(
            '/(?<label>[\p{L}°][\p{L}°\s\'’&]{0,40}?)\s*:/u',
            function (array $m) use ($report) {
                $field = $this->resolveIdentityField($m['label']);
                $value = $field !== null ? $this->identityValue($field, $report) : null;

                return $value !== null && $value !== '' ? $m[0].' '.$value.' ' : $m[0];
            },
            $text
        );

        if ($newText === null || $newText === $text) {
            return;
        }

        $this->setParagraphText($paragraph, trim(preg_replace('/\s+/u', ' ', $newText) ?? $newText));
    }

    private function resolveIdentityField(string $label): ?string
    {
        // Comparaison mot par mot (et non sous-chaîne) : « prénom » contient
        // littéralement « nom », donc un simple str_contains() se tromperait.
        $words = array_filter(explode(' ', $this->normalize($label)), fn ($w) => $w !== '');

        if ($words === []) {
            return null;
        }

        $hasWord = fn (string $needle) => in_array($needle, $words, true);

        return match (true) {
            $hasWord('nom') && ($hasWord('prenom') || $hasWord('prenoms')) => 'patient_full_name',
            $hasWord('nom') => 'patient_name',
            $hasWord('prenom') || $hasWord('prenoms') => null,
            $hasWord('age') => 'patient_age',
            $hasWord('sexe') => 'patient_sex',
            $hasWord('date') => 'exam_date',
            $hasWord('medecin') || $hasWord('prescripteur') => 'prescriber',
            $hasWord('dossier') || $words === ['n'] => 'file_number',
            default => null,
        };
    }

    private function identityValue(string $field, Report $report): ?string
    {
        return match ($field) {
            'patient_full_name', 'patient_name' => $report->patient_name,
            'patient_age' => $report->patient_age,
            'patient_sex' => $report->patient_sex,
            'exam_date' => $report->exam_date?->translatedFormat('d/m/Y'),
            'prescriber' => $report->prescriber,
            'file_number' => $report->file_number,
            default => null,
        };
    }

    /**
     * Pour une section à texte unique (Technique, Conclusion) qui peut être
     * répartie sur plusieurs paragraphes dans le template source (ex.
     * CHRACERH) : le nouveau texte va dans le premier paragraphe, les
     * éventuels paragraphes supplémentaires sont supprimés.
     *
     * @param  array<int, DOMElement>  $paragraphs
     */
    private function replaceSingleTextSection(array $paragraphs, ?string $text): void
    {
        if ($paragraphs === [] || empty($text)) {
            return;
        }

        $this->setParagraphText($paragraphs[0], $text);

        for ($i = 1; $i < count($paragraphs); $i++) {
            $paragraphs[$i]->parentNode->removeChild($paragraphs[$i]);
        }
    }

    /**
     * @param  array<int, DOMElement>  $originalParagraphs
     * @param  array<int, array{text: string, abnormal?: bool, heading?: bool}>  $results
     */
    private function replaceResults(DOMDocument $dom, array $originalParagraphs, array $results): void
    {
        if ($originalParagraphs === []) {
            return;
        }

        $count = min(count($originalParagraphs), count($results));

        for ($i = 0; $i < $count; $i++) {
            $this->setParagraphText($originalParagraphs[$i], $results[$i]['text']);
            $this->setParagraphColor($originalParagraphs[$i], ! empty($results[$i]['abnormal']) ? self::ANOMALY_COLOR : null);
        }

        // Surplus de lignes originales (résultats dictés en moins que le template) : on les vide.
        for ($i = $count; $i < count($originalParagraphs); $i++) {
            $originalParagraphs[$i]->parentNode->removeChild($originalParagraphs[$i]);
        }

        // Constatations supplémentaires (résultats dictés en plus) : on clone la dernière ligne.
        $template = $originalParagraphs[$count - 1] ?? $originalParagraphs[0];

        for ($i = $count; $i < count($results); $i++) {
            $clone = $template->cloneNode(true);
            $this->setParagraphText($clone, $results[$i]['text']);
            $this->setParagraphColor($clone, ! empty($results[$i]['abnormal']) ? self::ANOMALY_COLOR : null);
            $template->parentNode->insertBefore($clone, $template->nextSibling);
        }
    }

    private function hasBulletRun(DOMElement $paragraph): bool
    {
        // Une ligne de résultat porte du texte dans au moins un run ; les
        // sous-titres d'organe (tout en gras) sont exclus par l'appelant via runQualityChecks.
        return $paragraph->getElementsByTagNameNS(self::NS, 't')->length > 0;
    }

    private function nodeText(DOMElement $node): string
    {
        $texts = [];

        foreach ($node->getElementsByTagNameNS(self::NS, 't') as $t) {
            $texts[] = $t->textContent;
        }

        return trim(implode('', $texts));
    }

    /**
     * Remplace le texte d'un paragraphe en réutilisant le rPr du DERNIER run
     * porteur de texte (police, taille, couleur d'origine) — jamais de
     * reconstruction de la mise en forme (R1). Le dernier run est préféré
     * au premier car certains paragraphes portent un intitulé en gras suivi
     * de la valeur en formatage normal sur la même ligne (ex. « Conclusion :
     * texte ») : c'est la mise en forme de la VALEUR qu'il faut reproduire.
     */
    private function setParagraphText(DOMElement $paragraph, string $text): void
    {
        $runs = $paragraph->getElementsByTagNameNS(self::NS, 'r');
        $templateRun = null;

        foreach ($runs as $run) {
            if ($run->getElementsByTagNameNS(self::NS, 't')->length > 0) {
                $templateRun = $run;
            }
        }

        if ($templateRun === null) {
            return;
        }

        $rPr = $templateRun->getElementsByTagNameNS(self::NS, 'rPr')->item(0);

        // Retire tous les runs existants, ne garde que le rPr d'origine.
        foreach (iterator_to_array($runs) as $run) {
            $paragraph->removeChild($run);
        }

        $dom = $paragraph->ownerDocument;
        $newRun = $dom->createElementNS(self::NS, 'w:r');

        if ($rPr !== null) {
            $newRun->appendChild($rPr->cloneNode(true));
        }

        $t = $dom->createElementNS(self::NS, 'w:t', htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $newRun->appendChild($t);
        $paragraph->appendChild($newRun);
    }

    /**
     * Applique (ou retire) la couleur rouge d'anomalie sur tous les runs
     * porteurs de texte d'un paragraphe, en conservant le reste de leur mise
     * en forme (gras, police...) — sans jamais ajouter de soulignement (R1).
     */
    private function setParagraphColor(DOMElement $paragraph, ?string $hexColor): void
    {
        foreach ($paragraph->getElementsByTagNameNS(self::NS, 'r') as $run) {
            $rPr = $run->getElementsByTagNameNS(self::NS, 'rPr')->item(0);

            if ($rPr === null) {
                if ($hexColor === null) {
                    continue;
                }

                $rPr = $paragraph->ownerDocument->createElementNS(self::NS, 'w:rPr');
                $run->insertBefore($rPr, $run->firstChild);
            }

            $existingColor = $rPr->getElementsByTagNameNS(self::NS, 'color')->item(0);
            $existingColor?->parentNode->removeChild($existingColor);

            if ($hexColor !== null) {
                $color = $paragraph->ownerDocument->createElementNS(self::NS, 'w:color');
                $color->setAttributeNS(self::NS, 'w:val', $hexColor);
                $rPr->appendChild($color);
            }
        }
    }

    /**
     * Retire le soulignement des runs d'un paragraphe de titre/intitulé
     * (R1), sans toucher au reste de sa mise en forme (police, gras,
     * couleur, majuscules). Certains templates institutionnels fournis
     * portent encore un <w:u/> hérité sur leurs titres ; la règle du cahier
     * des charges prime sur la préservation à l'identique de ce détail.
     */
    private function stripUnderline(DOMElement $paragraph): void
    {
        foreach ($paragraph->getElementsByTagNameNS(self::NS, 'rPr') as $rPr) {
            foreach (iterator_to_array($rPr->getElementsByTagNameNS(self::NS, 'u')) as $underline) {
                $rPr->removeChild($underline);
            }
        }
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $transliterated !== false ? $transliterated : $value;
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
