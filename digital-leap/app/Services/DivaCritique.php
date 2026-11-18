<?php
declare(strict_types=1);

namespace Leap\Services;

/**
 * Report critique against the eight criteria printed on guidebook pages 24-25.
 *
 * This never produces a mark and never rewrites a sentence. It reports what it
 * can see in the submitted text, quotes the student's own words as evidence,
 * and points at the guidebook page that explains the criterion.
 */
trait DivaCritique
{
    /**
     * Read an uploaded report into plain text without keeping the file.
     *
     * @param array{name?:string,tmp_name?:string,error?:int,size?:int} $file
     * @return array{ok:bool,format:string,text:string,stats:array,message:string}
     */
    public static function readDocument(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return self::refusal('php', 'That file is larger than this server accepts. Keep the report under 5 MB.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            return self::refusal('php', 'No file arrived. Choose a .docx file, or paste the text into the box.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_file($tmp)) {
            return self::refusal('php', 'The upload could not be read. Try again.');
        }
        if ($size <= 0 || $size > self::MAX_UPLOAD) {
            return self::refusal('php', 'Keep the report under 5 MB.');
        }

        $name = mb_strtolower((string) ($file['name'] ?? ''));
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return self::refusal('pdf', 'DIVA cannot read PDF text offline in this build. Upload the same report as .docx, or paste the text into the box.');
        }
        if ($extension === 'doc') {
            return self::refusal('doc', 'The old .doc format is not readable offline. Save as .docx (Word: File > Save As > Word Document) or paste the text.');
        }
        if ($extension !== 'docx') {
            return self::refusal('unsupported', 'Only .docx files can be read. Paste the text into the box if your report is in another format.');
        }

        try {
            $read = self::docx($tmp);
        } catch (\RuntimeException $exception) {
            return self::refusal('docx', $exception->getMessage());
        }

        return [
            'ok' => true,
            'format' => 'docx',
            'text' => $read['text'],
            'stats' => $read['stats'],
            'message' => '',
        ];
    }

    /**
     * @param array{stats?:array,format?:string} $meta
     */
    public static function critique(string $text, array $meta = []): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if ($text === '') {
            return [
                'ok' => false,
                'message' => 'There was no readable text. A .docx file with text in the body works best; pictures of text cannot be read.',
                'words' => 0,
                'criteria' => [],
                'layout' => [],
                'note' => '',
            ];
        }

        $words = self::wordCount($text);
        $blocks = preg_split('/\n\s*\n/', $text) ?: [$text];
        $criteria = [];

        foreach (self::RUBRIC as $criterion) {
            $best = null;
            $bestRank = 0;
            $bestWords = 0;
            foreach ($blocks as $block) {
                $lines = preg_split('/\n/', $block) ?: [];
                $rank = 0;
                if ($lines !== [] && preg_match($criterion['pattern'], (string) $lines[0])) {
                    $rank = 2;
                } elseif (preg_match($criterion['pattern'], $block)) {
                    $rank = 1;
                }
                if ($rank === 0) {
                    continue;
                }
                $blockWords = self::wordCount($block);
                if ($rank > $bestRank || ($rank === $bestRank && $blockWords > $bestWords)) {
                    $best = $block;
                    $bestRank = $rank;
                    $bestWords = $blockWords;
                }
            }

            if ($best === null) {
                $status = 'missing';
            } elseif ($bestWords < 45) {
                $status = 'thin';
            } else {
                $status = 'present';
            }

            $criteria[] = [
                'key' => $criterion['key'],
                'label' => $criterion['label'],
                'weight' => $criterion['weight'],
                'page' => $criterion['page'],
                'status' => $status,
                'evidence' => $best === null ? '' : self::quote($best),
                'guidance' => $criterion['guidance'],
            ];
        }

        $stats = $meta['stats'] ?? [];
        $layout = self::layoutChecks($text, is_array($stats) ? $stats : [], $words);

        return [
            'ok' => true,
            'message' => '',
            'format' => (string) ($meta['format'] ?? 'text'),
            'words' => $words,
            'criteria' => $criteria,
            'layout' => $layout,
            'note' => 'DIVA does not give a mark, and it does not rewrite your text. Compare each point with the guidebook pages shown.',
        ];
    }

    /** @return array{text:string,stats:array} */
    private static function docx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('That file is not a readable .docx package. Re-save it from Word and try again.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $images = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with((string) $zip->getNameIndex($i), 'word/media/')) {
                $images++;
            }
        }
        $zip->close();

        if (!is_string($xml) || $xml === '') {
            throw new \RuntimeException('That .docx has no document body. Re-save it from Word and try again.');
        }
        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new \RuntimeException('That .docx contains a document type declaration, so it was refused. Re-save it from Word.');
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new \RuntimeException('That .docx body could not be parsed. Re-save it from Word and try again.');
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = 0;
        $headings = 0;
        $tables = $xpath->query('//w:tbl')->length;
        $chunks = [];
        $mergeNext = false;

        foreach ($xpath->query('//w:p') as $paragraph) {
            $text = '';
            foreach ($xpath->query('.//w:t', $paragraph) as $node) {
                $text .= $node->nodeValue;
            }
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
            if ($text === '') {
                continue;
            }

            $style = '';
            $styleNode = $xpath->query('./w:pPr/w:pStyle/@w:val', $paragraph);
            if ($styleNode !== false && $styleNode->length > 0) {
                $style = strtolower((string) $styleNode->item(0)->nodeValue);
            }
            if (str_starts_with($style, 'heading') || str_starts_with($style, 'title')) {
                $headings++;
                $chunks[] = $text;
                $mergeNext = true;
                continue;
            }

            $paragraphs++;
            if ($mergeNext && $chunks !== []) {
                // A heading and its first paragraph form one readable block.
                $chunks[count($chunks) - 1] .= "\n" . $text;
            } else {
                $chunks[] = $text;
            }
            $mergeNext = false;
        }

        $text = trim(implode("\n\n", $chunks));
        if ($text === '') {
            throw new \RuntimeException('That .docx has no text in the body. Text inside images cannot be read.');
        }

        return [
            'text' => $text,
            'stats' => [
                'headings' => $headings,
                'paragraphs' => $paragraphs,
                'tables' => $tables,
                'images' => $images,
            ],
        ];
    }

    /**
     * @param array{headings?:int,paragraphs?:int,tables?:int,images?:int} $stats
     * @return list<array{label:string,status:string,detail:string}>
     */
    private static function layoutChecks(string $text, array $stats, int $words): array
    {
        $headings = (int) ($stats['headings'] ?? 0);
        $plainHeadings = 0;
        foreach (preg_split('/\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || strlen($line) > 80) {
                continue;
            }
            if (preg_match('/^(chapter|section)\s+\d+|^\d+(\.\d+)*\s+\p{Lu}|^\p{Lu}[\p{Lu}\s&\-]{4,}$/u', $line)) {
                $plainHeadings++;
            }
        }
        if ($headings === 0) {
            $headings = $plainHeadings;
        }

        $hasContents = (bool) preg_match('/(^|\n)\s*(table of )?contents\b/i', $text);
        $hasAppendix = (bool) preg_match('/(^|\n)\s*(appendix|appendices)\b/i', $text);
        $hasReferences = (bool) preg_match('/(^|\n)\s*(references|bibliography)\b/i', $text);

        $sentences = preg_split('/(?<=[.!?])\s+/u', $text) ?: [];
        $sentenceLengths = [];
        foreach ($sentences as $sentence) {
            $length = self::wordCount($sentence);
            if ($length > 0) {
                $sentenceLengths[] = $length;
            }
        }
        $average = $sentenceLengths === [] ? 0 : (int) round(array_sum($sentenceLengths) / count($sentenceLengths));
        $longest = $sentenceLengths === [] ? 0 : max($sentenceLengths);
        $readability = $average === 0
            ? 'No sentences could be measured.'
            : 'Average sentence length ' . $average . ' words, longest ' . $longest . '.';
        if ($average > 30) {
            $readability .= ' Very long sentences are hard to read.';
        }

        return [
            [
                'label' => 'Length',
                'status' => 'manual',
                'detail' => $words . ' words in the body. Compare this with the length your faculty expects.',
            ],
            [
                'label' => 'Headings and organisation',
                'status' => $headings >= 5 ? 'present' : ($headings >= 2 ? 'thin' : 'missing'),
                'detail' => $headings . ' heading' . ($headings === 1 ? '' : 's') . ' and ' . (int) ($stats['paragraphs'] ?? 0) . ' other paragraphs detected.',
            ],
            [
                'label' => 'Contents page',
                'status' => $hasContents ? 'present' : 'missing',
                'detail' => $hasContents ? 'A contents section was found.' : 'No contents section was found.',
            ],
            [
                'label' => 'Additional material',
                'status' => ($hasAppendix || $hasReferences) ? 'present' : 'thin',
                'detail' => $hasAppendix
                    ? 'An appendix was found.'
                    : ($hasReferences ? 'A references section was found.' : 'No appendix or references section was found.'),
            ],
            [
                'label' => 'Visuals and tables',
                'status' => (((int) ($stats['images'] ?? 0)) + ((int) ($stats['tables'] ?? 0))) > 0 ? 'present' : 'thin',
                'detail' => (int) ($stats['images'] ?? 0) . ' image(s) and ' . (int) ($stats['tables'] ?? 0) . ' table(s) detected.',
            ],
            [
                'label' => 'Readability',
                'status' => $average === 0 ? 'missing' : ($average <= 30 ? 'present' : 'thin'),
                'detail' => $readability,
            ],
            [
                'label' => 'Page numbers and justification',
                'status' => 'manual',
                'detail' => 'DIVA cannot see page numbers or alignment reliably. Check the print preview yourself.',
            ],
        ];
    }

    /** @return array{ok:bool,format:string,text:string,stats:array,message:string} */
    private static function refusal(string $format, string $message): array
    {
        return ['ok' => false, 'format' => $format, 'text' => '', 'stats' => [], 'message' => $message];
    }

    private static function wordCount(string $text): int
    {
        return (int) preg_match_all("/[\p{L}\p{N}][\p{L}\p{N}'’-]*/u", $text);
    }

    private static function quote(string $line): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');
        if (mb_strlen($line) > 140) {
            $line = mb_substr($line, 0, 137) . '...';
        }
        return $line;
    }
}
