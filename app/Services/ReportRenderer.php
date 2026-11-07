<?php

namespace App\Services;

use Dompdf\Dompdf;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class ReportRenderer
{
    public const MIME = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf', 'csv' => 'text/csv'];

    private function text($value): string
    {
        return $value === null ? 'Not reported' : (is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value);
    }

    public function render(array $report, string $format): string
    {
        abort_unless(isset(self::MIME[$format]), 422);
        $count = array_sum(array_map(fn ($t) => count($t['rows']), $report['tables']));
        if (in_array($format, ['pdf', 'docx']) && $count > 5000) {
            throw ValidationException::withMessages(['format' => 'This report contains more than 5,000 rows. Use Excel or CSV, or narrow the filters. No records have been omitted.']);
        }

        return match ($format) {
            'xlsx' => $this->excel($report),'docx' => $this->word($report),'pdf' => $this->pdf($report),default => $this->csv($report)
        };
    }

    private function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+');
        $write = function (array $row) use ($stream) {
            fputcsv($stream, array_map(function ($v) {
                if (is_int($v) || is_float($v)) {
                    return (string) $v;
                }
                $v = $this->text($v);

                return preg_match('/^[\s]*[=+@-]|^[\t\r\n]/u', $v) ? "'".$v : $v;
            }, $row), ',', '"', '');
        };
        try {
            $write([$report['title']]);
            foreach ($report['metadata'] as $k => $v) {
                $write([$k, $v]);
            }foreach ($report['notes'] ?? [] as $note) {
                $write(['Note', $note]);
            }
            foreach ($report['tables'] as $t) {
                $write([]);
                $write([$t['name']]);
                if ($t['note'] ?? '') {
                    $write(['Note', $t['note']]);
                }$write($t['headers']);
                foreach ($t['rows'] as $row) {
                    $write($row);
                }
            }rewind($stream);

            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    private function excel(array $report): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);
        $names = [];
        $metadata = [['name' => 'Report information', 'headers' => ['Item', 'Value'], 'rows' => array_merge(array_map(fn ($k, $v) => [$k, $v], array_keys($report['metadata']), array_values($report['metadata'])), array_map(fn ($n) => ['Note', $n], $report['notes'] ?? [])), 'note' => '']];
        try {
            foreach ([...$metadata, ...$report['tables']] as $table) {
                $name = mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $table['name']), 0, 27) ?: 'Report';
                $base = $name;
                $i = 2;
                while (in_array($name, $names, true)) {
                    $name = $base.' '.$i++;
                }$names[] = $name;
                $sheet = $book->createSheet();
                $sheet->setTitle($name);
                $columns = count($table['headers']);
                $last = Coordinate::stringFromColumnIndex($columns);
                $sheet->setCellValueExplicit('A1', $table['name'], DataType::TYPE_STRING);
                $sheet->mergeCells("A1:{$last}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->setCellValueExplicit('A2', $table['note'] ?? '', DataType::TYPE_STRING);
                $sheet->mergeCells("A2:{$last}2");
                $sheet->getStyle('A2')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(2)->setRowHeight(32);
                foreach ([$table['headers'], ...$table['rows']] as $index => $row) {
                    foreach ($row as $column => $value) {
                        $cell = Coordinate::stringFromColumnIndex($column + 1).($index + 4);
                        if ($index > 0 && (is_int($value) || is_float($value))) {
                            $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_NUMERIC);
                        } else {
                            $sheet->setCellValueExplicit($cell, $this->text($value), DataType::TYPE_STRING);
                        }
                    }
                }
                $bottom = max(4, count($table['rows']) + 4);
                $sheet->freezePane('A5');
                $sheet->setAutoFilter("A4:{$last}{$bottom}");
                $sheet->getStyle("A4:{$last}4")->getFill()->setFillType('solid')->getStartColor()->setARGB('FF214F85');
                $sheet->getStyle("A4:{$last}4")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle("A4:{$last}{$bottom}")->getAlignment()->setVertical('top')->setWrapText(true);
                foreach ($table['headers'] as $c => $header) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c + 1))->setWidth(min(45, max(15, mb_strlen($header) + 3)));
                }
                for ($row = 5; $row <= $bottom; $row += 2) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFF0F5FC');
                }
                $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(4, 4);
            }$book->setActiveSheetIndex(0);
            $stream = fopen('php://temp', 'w+');
            try {
                (new Xlsx($book))->save($stream);
                rewind($stream);

                return stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function printableRows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $chunks = array_map(fn ($v) => is_string($v) && mb_strlen($v) > 500 ? mb_str_split($v, 500) : [$v], $row);
            $n = max(array_map('count', $chunks));
            for ($i = 0; $i < $n; $i++) {
                $result[] = array_map(fn ($parts) => array_key_exists($i, $parts) ? $parts[$i] : '', $chunks);
            }
        }

        return $result;
    }

    private function pdf(array $report): string
    {
        $html = '<html><head><meta charset="utf-8"><style>@page{margin:24px 24px 36px}body{font-family:DejaVu Sans;font-size:9px;color:#243950}h1{font-size:18px;color:#000}h2{font-size:13px;color:#000;page-break-after:avoid}p{line-height:1.5}table{border-collapse:collapse;width:100%;table-layout:fixed;margin-bottom:16px}th,td{border:1px solid #ccd9e7;padding:5px;word-wrap:break-word;vertical-align:top}th{background:#214f85;color:white;font-weight:bold}tr:nth-child(even){background:#f0f5fc}thead{display:table-header-group}.note{font-size:8px;color:#526981}</style></head><body><h1>'.e($report['title']).'</h1>';
        foreach ($report['metadata'] as $key => $v) {
            $html .= '<p><b>'.e($key).':</b> '.e($v).'</p>';
        }
        foreach ($report['notes'] ?? [] as $note) {
            $html .= '<p class="note">'.e($note).'</p>';
        }
        foreach ($report['tables'] as $t) {
            $html .= '<h2>'.e($t['name']).'</h2><p class="note">'.e($t['note'] ?? '').'</p><table><thead><tr>';
            foreach ($t['headers'] as $h) {
                $html .= '<th>'.e($h).'</th>';
            }$html .= '</tr></thead><tbody>';
            foreach ($this->printableRows($t['rows']) as $row) {
                $html .= '<tr>';
                foreach ($row as $v) {
                    $html .= '<td>'.nl2br(e($this->text($v))).'</td>';
                }$html .= '</tr>';
            }if (! $t['rows']) {
                $html .= '<tr><td colspan="'.count($t['headers']).'">No records in this selection.</td></tr>';
            }$html .= '</tbody></table>';
        }
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isJavascriptEnabled' => false]);
        $pdf->loadHtml($html.'</body></html>');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getCanvas()->page_text(24, 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8);

        return $pdf->output();
    }

    private function xml(string $text): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function paragraph(string $text, string $style = 'Normal'): string
    {
        return '<w:p><w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr><w:r><w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>';
    }

    private function word(array $report): string
    {
        $body = $this->paragraph($report['title'], 'Title');
        foreach ($report['metadata'] as $key => $value) {
            $body .= $this->paragraph($key.': '.$value);
        }foreach ($report['notes'] ?? [] as $note) {
            $body .= $this->paragraph($note);
        }
        foreach ($report['tables'] as $table) {
            $body .= $this->paragraph($table['name'], 'Heading1');
            if ($table['note'] ?? '') {
                $body .= $this->paragraph($table['note']);
            }$n = count($table['headers']);
            $width = (int) floor(15000 / max(1, $n));
            $body .= '<w:tbl><w:tblPr><w:tblW w:w="15000" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="CCD9E7"/><w:bottom w:val="single" w:sz="4" w:color="CCD9E7"/><w:insideH w:val="single" w:sz="4" w:color="CCD9E7"/><w:insideV w:val="single" w:sz="4" w:color="CCD9E7"/></w:tblBorders><w:tblCellMar><w:top w:w="70" w:type="dxa"/><w:bottom w:w="70" w:type="dxa"/><w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>'.str_repeat('<w:gridCol w:w="'.$width.'"/>', $n).'</w:tblGrid>';
            $rows = [$table['headers'], ...$this->printableRows($table['rows'] ?: [array_fill(0, $n, 'No records in this selection.')])];
            foreach ($rows as $index => $row) {
                $body .= '<w:tr><w:trPr>'.($index === 0 ? '<w:tblHeader/>' : '').'<w:cantSplit/></w:trPr>';
                foreach ($row as $value) {
                    $fill = $index === 0 ? '214F85' : ($index % 2 === 0 ? 'F0F5FC' : 'FFFFFF');
                    $body .= '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/><w:shd w:fill="'.$fill.'"/></w:tcPr><w:p><w:pPr><w:spacing w:after="40"/></w:pPr><w:r><w:rPr><w:sz w:val="'.($n > 6 ? 18 : 20).'"/>'.($index === 0 ? '<w:b/><w:color w:val="FFFFFF"/>' : '').'</w:rPr><w:t xml:space="preserve">'.$this->xml($this->text($value)).'</w:t></w:r></w:p></w:tc>';
                }$body .= '</w:tr>';
            }$body .= '</w:tbl>'.$this->paragraph('');
        }
        $body .= '<w:sectPr><w:footerReference w:type="default" r:id="rId2"/><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="800" w:right="900" w:bottom="900" w:left="900" w:footer="400"/></w:sectPr>';
        $path = tempnam(sys_get_temp_dir(), 'mdes-docx-');
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Unable to create Word document.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
            $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>');
            $zip->addFromString('word/styles.xml', '<?xml version="1.0"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/><w:color w:val="000000"/></w:rPr></w:rPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:pPr><w:spacing w:after="100"/></w:pPr></w:style><w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:sz w:val="36"/><w:color w:val="000000"/></w:rPr></w:style><w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="120"/></w:pPr><w:rPr><w:b/><w:sz w:val="26"/><w:color w:val="000000"/></w:rPr></w:style></w:styles>');
            $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'</w:body></w:document>');
            $zip->addFromString('word/footer1.xml', '<?xml version="1.0"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>MDES | Page </w:t></w:r><w:fldSimple w:instr="PAGE"/><w:r><w:t> of </w:t></w:r><w:fldSimple w:instr="NUMPAGES"/></w:p></w:ftr>');
            $zip->close();

            return file_get_contents($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
