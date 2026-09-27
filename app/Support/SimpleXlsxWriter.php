<?php

namespace App\Support;

use ZipArchive;

/**
 * Minimal XLSX writer (single or multi-sheet) — no PhpSpreadsheet/GD required.
 */
class SimpleXlsxWriter
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function write(string $path, array $headers, array $rows = [], string $sheetName = 'Sheet1'): void
    {
        $this->writeSheets($path, [
            [
                'name' => $sheetName,
                'headers' => $headers,
                'rows' => $rows,
            ],
        ]);
    }

    /**
     * @param  list<array{name: string, headers: list<string>, rows?: list<list<string|int|float|null>>}>  $sheets
     */
    public function writeSheets(string $path, array $sheets): void
    {
        if ($sheets === []) {
            throw new \InvalidArgumentException('At least one sheet is required.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Unable to create XLSX at {$path}");
        }

        $overrides = '';
        $workbookSheets = '';
        $rels = '';

        foreach (array_values($sheets) as $i => $sheet) {
            $n = $i + 1;
            $name = $this->safeSheetName((string) ($sheet['name'] ?? "Sheet{$n}"), $n);
            $headers = $sheet['headers'] ?? [];
            $rows = $sheet['rows'] ?? [];
            $sheetRows = array_merge([$headers], $rows);
            $sheetXml = $this->sheetXml($sheetRows);

            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbookSheets .= '<sheet name="'.htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $zip->addFromString("xl/worksheets/sheet{$n}.xml", $sheetXml);
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$overrides
            .'</Types>');

        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$workbookSheets.'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>');

        $zip->close();
    }

    protected function safeSheetName(string $name, int $fallbackIndex): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', '', $name) ?: "Sheet{$fallbackIndex}";
        $name = mb_substr($name, 0, 31);

        return $name !== '' ? $name : "Sheet{$fallbackIndex}";
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    protected function sheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($rows as $rIndex => $cols) {
            $rowNum = $rIndex + 1;
            $xml .= '<row r="'.$rowNum.'">';
            foreach (array_values($cols) as $cIndex => $value) {
                $cell = $this->columnLetter($cIndex).$rowNum;
                $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';

        return $xml;
    }

    protected function columnLetter(int $index): string
    {
        $index++;
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }
}
