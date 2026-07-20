<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Analyse un DOCX de comptes rendus normaux d'un hôpital et en extrait la
 * structure : un examen par bloc (délimité par un saut de page et/ou un
 * titre « Compte Rendu de... »), avec ses sections TECHNIQUE / RÉSULTATS /
 * CONCLUSION, ainsi que la couleur dominante des titres.
 *
 * Sert à la fois à générer le catalogue initial des 5 hôpitaux (étape 2) et
 * de moteur à l'assistant « Ajouter un hôpital » (étape 8, F2).
 */
class HospitalDocxParser
{
    private const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Titre générique répété sur chaque bloc dans certains templates (ex. CHM) : le vrai nom vient du champ "Examen :". */
    private const GENERIC_TITLE_RE = '/^compte[\s-]*rendu\s+d[\'’]imagerie\s+m[ée]dicale/iu';

    private const TITLE_RE = '/^compte[\s-]*rendu\b/iu';

    private const HEADING_MAP = [
        'indication' => '/^indications?\s*:?\s*$|^indications?\s*:/iu',
        'technique' => '/^techniques?\s*:?\s*$|^techniques?\s*:/iu',
        'results' => '/^(r[ée]sultats?|descriptif|description)\s*:?\s*$/iu',
        'conclusion' => '/^conclusion\s*:?\s*$|^conclusion\s*:/iu',
        'identification' => '/^(identification|identit[ée])\b/iu',
    ];

    private const SIGNATURE_RE = '/^(dr\.?\s|docteur\s|pr\.?\s|professeur\s|radiologue$|m[ée]decin radiologue|fait\s+à\s|agr[ée]g[ée]e?\s)/iu';

    private const MODALITY_KEYWORDS = [
        'échographie doppler' => 'echographie_doppler',
        'echographie doppler' => 'echographie_doppler',
        'échographie' => 'echographie',
        'echographie' => 'echographie',
        'radiographie' => 'radiographie',
        'orthopantomogramme' => 'panoramique_dentaire',
        'panoramique' => 'panoramique_dentaire',
        'hystérosalpingographie' => 'hsg',
        'hystérosonographie' => 'hsg',
        'urographie' => 'uiv',
        'urétrocystographie' => 'ucrm',
        'transit' => 'tog',
        'lavement' => 'lavement_baryte',
    ];

    /**
     * @return array{exams: array<int, array<string, mixed>>, colors: array<string, string>, media: array<int, string>}
     */
    public function parse(string $docxPath): array
    {
        $xmlContents = $this->readPart($docxPath, 'word/document.xml');
        $dom = new DOMDocument;
        $dom->loadXML($xmlContents);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS);

        $lines = $this->flattenBody($xpath);
        $exams = $this->segmentExams($lines);
        $colors = $this->extractColors($xmlContents);

        return [
            'exams' => $exams,
            'colors' => $colors,
            'media' => $this->listMedia($docxPath),
        ];
    }

    private function readPart(string $docxPath, string $part): string
    {
        $zip = new ZipArchive;

        if ($zip->open($docxPath) !== true) {
            throw new RuntimeException("Impossible d'ouvrir le fichier DOCX : {$docxPath}");
        }

        $contents = $zip->getFromName($part);
        $zip->close();

        if ($contents === false) {
            throw new RuntimeException("Partie introuvable dans le DOCX : {$part}");
        }

        return $contents;
    }

    /**
     * @return array<int, array{text: string, page_break: bool}>
     */
    private function flattenBody(DOMXPath $xpath): array
    {
        $body = $xpath->query('/w:document/w:body')->item(0);

        if (! $body instanceof DOMElement) {
            return [];
        }

        $lines = [];
        $this->walkNode($body, $xpath, $lines);

        return $lines;
    }

    /**
     * @param  array<int, array{text: string, page_break: bool}>  $lines
     */
    private function walkNode(DOMElement $node, DOMXPath $xpath, array &$lines): void
    {
        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $local = $child->localName;

            if ($local === 'p') {
                $text = $this->paragraphText($child, $xpath);
                $pageBreak = $xpath->query('.//w:br[@w:type="page"]', $child)->length > 0;

                if (trim($text) !== '' || $pageBreak) {
                    $lines[] = [
                        'text' => trim($text),
                        'page_break' => $pageBreak,
                        'bold' => $this->isParagraphBold($child, $xpath),
                    ];
                }
            } elseif ($local === 'tbl') {
                foreach ($xpath->query('.//w:tr', $child) as $row) {
                    foreach ($xpath->query('./w:tc', $row) as $cell) {
                        $this->walkNode($cell, $xpath, $lines);
                    }
                }
            }
        }
    }

    private function paragraphText(DOMElement $paragraph, DOMXPath $xpath): string
    {
        $texts = [];

        foreach ($xpath->query('.//w:t', $paragraph) as $t) {
            $texts[] = $t->textContent;
        }

        return implode('', $texts);
    }

    /**
     * Un paragraphe est considéré en gras si tous ses runs porteurs de texte
     * le sont : sert à distinguer les sous-titres d'organe (ex. « Foie »,
     * « Pancréas » dans les comptes rendus d'échographie CHM) des lignes de
     * constatation à puce.
     */
    private function isParagraphBold(DOMElement $paragraph, DOMXPath $xpath): bool
    {
        $textRuns = $xpath->query('.//w:r[w:t[normalize-space(.) != ""]]', $paragraph);

        if ($textRuns->length === 0) {
            return false;
        }

        foreach ($textRuns as $run) {
            $bold = $xpath->query('./w:rPr/w:b', $run)->item(0);

            if (! $bold instanceof DOMElement) {
                return false;
            }

            $val = $bold->getAttributeNS(self::NS, 'val');

            if ($val !== '' && in_array(mb_strtolower($val), ['0', 'false', 'off'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array{text: string, page_break: bool}>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function segmentExams(array $lines): array
    {
        $exams = [];
        $current = null;
        $section = null;

        $flush = function () use (&$current, &$exams) {
            if ($current !== null && $current['title'] !== '') {
                $current['technique'] = trim(implode(' ', $current['technique']));
                $current['indication'] = trim(implode(' ', $current['indication'])) ?: null;
                $current['conclusion'] = trim(implode(' ', $current['conclusion'])) ?: null;
                unset($current['_generic_title']);
                $exams[] = $current;
            }
        };

        foreach ($lines as $line) {
            $text = $line['text'];

            if ($text === '') {
                continue;
            }

            $isGenericTitle = preg_match(self::GENERIC_TITLE_RE, $text) === 1;
            $isTitle = $isGenericTitle || preg_match(self::TITLE_RE, $text) === 1;

            if ($isTitle) {
                $flush();
                [$cleanTitle, $requiresSide] = $this->cleanTitle($text);
                $current = [
                    'title' => $isGenericTitle ? '' : $cleanTitle,
                    'heading' => $isGenericTitle ? '' : $text,
                    'requires_side' => $requiresSide,
                    'modality' => $this->guessModality($text),
                    'indication' => [],
                    'technique' => [],
                    'results' => [],
                    'conclusion' => [],
                    '_generic_title' => $isGenericTitle,
                ];
                $section = null;

                continue;
            }

            if ($current === null) {
                continue;
            }

            // Titre réel porté par un champ "Examen : ..." (templates génériques type CHM).
            if ($current['_generic_title'] && preg_match('/^examen\s*:\s*(.+)$/iu', $text, $m) === 1) {
                [$cleanTitle, $requiresSide] = $this->cleanTitle(trim($m[1]));
                $current['title'] = $cleanTitle;
                $current['heading'] = trim($m[1]);
                $current['requires_side'] = $current['requires_side'] || $requiresSide;
                $current['modality'] = $current['modality'] ?? $this->guessModality($m[1]);

                continue;
            }

            $matchedHeading = $this->matchHeading($text);

            if ($matchedHeading !== null) {
                $section = $matchedHeading === 'identification' ? null : $matchedHeading;
                $remainder = $this->stripHeadingPrefix($text);

                if ($section !== null && $remainder !== '') {
                    $this->appendToSection($current, $section, $remainder, false);
                }

                continue;
            }

            if ($section === 'identification' || $section === null) {
                // Certains templates (ex. HMR1) portent la mention de latéralité dans
                // le bloc d'identification plutôt que dans le titre de l'examen.
                if (preg_match('/côté|☐\s*(d(roit)?|g(auche)?)\b/iu', $text) === 1) {
                    $current['requires_side'] = true;
                }

                continue;
            }

            if (preg_match(self::SIGNATURE_RE, $text) === 1) {
                $section = null;

                continue;
            }

            $this->appendToSection($current, $section, $text, $line['bold']);
        }

        $flush();

        return array_values(array_filter($exams, fn ($exam) => $exam['title'] !== ''));
    }

    private function appendToSection(array &$exam, string $section, string $text, bool $bold): void
    {
        if ($section === 'results') {
            $hasBullet = $this->hasBulletMarker($text);

            $exam['results'][] = [
                'text' => $hasBullet ? $this->stripBullet($text) : $text,
                'abnormal' => false,
                // Sous-titre d'organe (ex. « Foie », « Pancréas ») plutôt qu'une
                // constatation à puce : rendu en gras sans puce à la génération (F3).
                'heading' => ! $hasBullet && $bold,
            ];
        } else {
            $exam[$section][] = $text;
        }
    }

    private function hasBulletMarker(string $text): bool
    {
        return preg_match('/^[•●▪\-–\*]\s*/u', $text) === 1;
    }

    private function matchHeading(string $text): ?string
    {
        foreach (self::HEADING_MAP as $key => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $key;
            }
        }

        return null;
    }

    private function stripHeadingPrefix(string $text): string
    {
        if (! str_contains($text, ':')) {
            return '';
        }

        $withoutPrefix = preg_replace('/^[^:]*:\s*/u', '', $text, 1);

        return trim($withoutPrefix ?? '');
    }

    private function stripBullet(string $text): string
    {
        return trim(preg_replace('/^[•●▪\-–\*]\s*/u', '', $text) ?? '');
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function cleanTitle(string $text): array
    {
        $requiresSide = (bool) preg_match('/côté|side|☐\s*d\b|☐\s*g\b/iu', $text);

        $clean = preg_replace('/\s*[—–-]\s*Côté\s*:.*/iu', '', $text) ?? $text;
        $clean = preg_replace('/^compte[\s-]*rendu\s+(de\s+|d[\'’]|du\s+|des\s+)?/iu', '', $clean) ?? $clean;
        $clean = trim($clean, " \t\n\r\0\x0B—–-:");

        return [$clean !== '' ? $clean : trim($text), $requiresSide];
    }

    private function guessModality(string $text): ?string
    {
        $lower = mb_strtolower($text);

        foreach (self::MODALITY_KEYWORDS as $needle => $modality) {
            if (str_contains($lower, $needle)) {
                return $modality;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function extractColors(string $xmlContents): array
    {
        preg_match_all('/w:color w:val="([0-9A-Fa-f]{6})"/', $xmlContents, $matches);
        $counts = array_count_values(array_map('strtoupper', $matches[1] ?? []));
        unset($counts['000000'], $counts['FFFFFF']);
        arsort($counts);

        $primary = array_key_first($counts) ?? '1F3864';

        return ['primary' => '#'.$primary];
    }

    /**
     * @return array<int, string>
     */
    private function listMedia(string $docxPath): array
    {
        $zip = new ZipArchive;
        $media = [];

        if ($zip->open($docxPath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if ($name !== false && str_starts_with($name, 'word/media/')) {
                    $media[] = $name;
                }
            }
            $zip->close();
        }

        return $media;
    }
}
