<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal XLSX reader (shared strings + inlineStr) — single or multi-sheet.
 */
class SimpleXlsxReader
{
    /**
     * Read the first worksheet (sheet1.xml).
     *
     * @return list<list<string>>
     */
    public function read(string $path): array
    {
        $sheets = $this->readAll($path);
        $first = reset($sheets);

        return $first !== false ? $first['rows'] : [];
    }

    /**
     * @return list<array{name: string, rows: list<list<string>>}>
     */
    public function readAll(string $path): array
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

        $sheetMeta = $this->workbookSheets($zip);
        $result = [];

        foreach ($sheetMeta as $meta) {
            $sheetXml = $zip->getFromName($meta['path']);
            if ($sheetXml === false) {
                continue;
            }
            $result[] = [
                'name' => $meta['name'],
                'rows' => $this->parseSheet($sheetXml, $shared),
            ];
        }

        $zip->close();

        if ($result === []) {
            throw new RuntimeException('XLSX has no readable worksheets');
        }

        return $result;
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    protected function workbookSheets(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false) {
            return [['name' => 'Sheet1', 'path' => 'xl/worksheets/sheet1.xml']];
        }

        $ridToTarget = [];
        if ($rels !== false) {
            $relsDoc = new \DOMDocument;
            $relsDoc->loadXML($rels);
            foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
                $id = $rel->getAttribute('Id');
                $target = str_replace('\\', '/', $rel->getAttribute('Target'));
                if ($id === '' || $target === '') {
                    continue;
                }
                // Targets in workbook.xml.rels are relative to xl/
                if (str_starts_with($target, '/')) {
                    $ridToTarget[$id] = ltrim($target, '/');
                } else {
                    $ridToTarget[$id] = 'xl/'.ltrim($target, './');
                }
            }
        }

        $doc = new \DOMDocument;
        $doc->loadXML($workbook);
        $sheets = [];
        $index = 0;
        foreach ($doc->getElementsByTagName('sheet') as $sheet) {
            $index++;
            $name = $sheet->getAttribute('name') ?: "Sheet{$index}";
            $rid = $sheet->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id'
            );
            if ($rid === '') {
                $rid = $sheet->getAttribute('r:id');
            }
            $path = $ridToTarget[$rid] ?? "xl/worksheets/sheet{$index}.xml";
            $sheets[] = ['name' => $name, 'path' => $path];
        }

        return $sheets !== [] ? $sheets : [['name' => 'Sheet1', 'path' => 'xl/worksheets/sheet1.xml']];
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
