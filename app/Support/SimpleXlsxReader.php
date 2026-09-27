<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal XLSX reader for import templates (shared strings + inlineStr).
 */
class SimpleXlsxReader
{
    /**
     * @return list<list<string>>
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Unable to open XLSX at {$path}");
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = $this->parseSharedStrings($sharedXml);
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('XLSX is missing sheet1.xml');
        }

        return $this->parseSheet($sheetXml, $shared);
    }

    /**
     * @return list<string>
     */
    protected function parseSharedStrings(string $xml): array
    {
        $strings = [];
        $doc = new \DOMDocument;
        $doc->loadXML($xml);
        foreach ($doc->getElementsByTagName('si') as $si) {
            $text = '';
            foreach ($si->getElementsByTagName('t') as $t) {
                $text .= $t->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    protected function parseSheet(string $xml, array $shared): array
    {
        $doc = new \DOMDocument;
        $doc->loadXML($xml);
        $rows = [];

        foreach ($doc->getElementsByTagName('row') as $row) {
            $cells = [];
            $maxCol = -1;
            $byCol = [];

            foreach ($row->getElementsByTagName('c') as $c) {
                $ref = $c->getAttribute('r');
                $col = $this->columnIndex($ref);
                $maxCol = max($maxCol, $col);
                $type = $c->getAttribute('t');
                $value = '';

                if ($type === 'inlineStr') {
                    $tNodes = $c->getElementsByTagName('t');
                    $value = $tNodes->length ? $tNodes->item(0)->textContent : '';
                } elseif ($type === 's') {
                    $v = $c->getElementsByTagName('v');
                    $idx = $v->length ? (int) $v->item(0)->textContent : 0;
                    $value = $shared[$idx] ?? '';
                } else {
                    $v = $c->getElementsByTagName('v');
                    $value = $v->length ? $v->item(0)->textContent : '';
                }

                $byCol[$col] = $value;
            }

            for ($i = 0; $i <= $maxCol; $i++) {
                $cells[] = $byCol[$i] ?? '';
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    protected function columnIndex(string $cellRef): int
    {
        if (! preg_match('/^([A-Z]+)/i', $cellRef, $m)) {
            return 0;
        }

        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }
}
