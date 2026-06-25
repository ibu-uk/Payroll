<?php
// Pure PHP XLSX writer - zero external dependencies, works on vanilla XAMPP/PHP
$periodId = (int)($_GET['period_id'] ?? 0);
$deptId   = (int)($_GET['dept_id']   ?? 0);
$period   = DB::row("SELECT * FROM payroll_periods WHERE id=?", [$periodId]);
if (!$period) die('Period not found');

$where  = $deptId ? "AND d.id=?" : "";
$params = $deptId ? [$periodId,$deptId] : [$periodId];
$items  = DB::rows("SELECT pi.*,e.name_en,e.name_ar,e.employee_no,e.bank_name,e.iban,
    d.name_en dept_en,j.title_en
    FROM payroll_items pi JOIN employees e ON e.id=pi.employee_id
    LEFT JOIN departments d ON d.id=e.department_id
    LEFT JOIN job_titles j ON j.id=e.job_title_id
    WHERE pi.payroll_period_id=? $where ORDER BY d.name_en,e.name_en", $params);

$s   = DB::row("SELECT * FROM settings WHERE id=1") ?? [];
$cur = $s['currency'] ?? 'KWD';
$label = preg_replace('/[^A-Za-z0-9_\-]/', '_', $period['period_label'] ?? 'Payroll');

// Build XLSX using ZipArchive + XML (built into PHP, no Composer)
function xlsEsc(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function buildXlsx(array $sheets): string {
    // Collect shared strings
    $strings = []; $strIndex = [];
    foreach ($sheets as $sh) {
        foreach ($sh['rows'] as $row) {
            foreach ($row as $cell) {
                if (!is_numeric($cell) && $cell !== null && $cell !== '') {
                    $k = (string)$cell;
                    if (!isset($strIndex[$k])) {
                        $strIndex[$k] = count($strings);
                        $strings[] = $k;
                    }
                }
            }
        }
    }

    $sharedStrings = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">';
    foreach ($strings as $s) $sharedStrings .= '<si><t xml:space="preserve">'.xlsEsc($s).'</t></si>';
    $sharedStrings .= '</sst>';

    // Styles
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
<fonts count="3"><font><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="10"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>
<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1E293B"/></fgColor></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0FDF4"/></fgColor></fill></fills>
<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="6">
<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"/>
<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"><alignment horizontal="center"/></xf>
<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0"/>
<xf numFmtId="4" fontId="1" fillId="3" borderId="1" xfId="0"/>
<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0"><alignment horizontal="left"/></xf>
<xf numFmtId="4" fontId="2" fillId="2" borderId="1" xfId="0"><alignment horizontal="right"/></xf>
</cellXfs></styleSheet>';

    function colLetter(int $n): string {
        $r = '';
        while ($n >= 0) { $r = chr(65 + ($n % 26)) . $r; $n = intdiv($n, 26) - 1; }
        return $r;
    }

    $zip = new ZipArchive();
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip->open($tmpFile, ZipArchive::OVERWRITE);

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach ($sheets as $i=>$sh) $contentTypes .= '<Override PartName="/xl/worksheets/sheet'.($i+1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $contentTypes .= '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';

    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId99" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId98" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    foreach ($sheets as $i=>$sh) {
        $wbRels .= '<Relationship Id="rId'.($i+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i+1).'.xml"/>';
        $wb .= '<Sheet name="'.xlsEsc($sh['name']).'" sheetId="'.($i+1).'" r:id="rId'.($i+1).'"/>';
    }
    $wbRels .= '</Relationships>';
    $wb .= '</sheets></workbook>';

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $wb);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
    $zip->addFromString('xl/sharedStrings.xml', $sharedStrings);
    $zip->addFromString('xl/styles.xml', $styles);

    foreach ($sheets as $si=>$sh) {
        $wsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($sh['rows'] as $ri=>$row) {
            $isHeader = $ri === 0;
            $isTotal  = isset($sh['total_row']) && $ri === $sh['total_row'];
            $wsXml .= '<row r="'.($ri+1).'">';
            foreach ($row as $ci=>$cell) {
                $col = colLetter($ci);
                $ref = $col.($ri+1);
                if ($cell === null || $cell === '') {
                    $wsXml .= '<c r="'.$ref.'"/>';
                } elseif (is_numeric($cell) && !$isHeader) {
                    $s = $isTotal ? '5' : '2';
                    if ($isHeader) $s = '5';
                    $wsXml .= '<c r="'.$ref.'" t="n" s="'.$s.'"><v>'.$cell.'</v></c>';
                } else {
                    $sidx = $strIndex[(string)$cell] ?? 0;
                    $s = $isHeader ? '1' : ($isTotal ? '4' : '0');
                    $wsXml .= '<c r="'.$ref.'" t="s" s="'.$s.'"><v>'.$sidx.'</v></c>';
                }
            }
            $wsXml .= '</row>';
        }
        $wsXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet'.($si+1).'.xml', $wsXml);
    }

    $zip->close();
    $content = file_get_contents($tmpFile);
    unlink($tmpFile);
    return $content;
}

// Build Sheet 1: Payroll Summary
$headers1 = ['#','Emp No.','Name','Department','Job Title','Basic Salary','Allowances','Gross','OT Amount','Deductions','Social Ins.','Tax','Net Salary ('.$cur.')','Status'];
$rows1 = [$headers1];
foreach ($items as $i=>$it) {
    $rows1[] = [$i+1,$it['employee_no'],$it['name_en'],$it['dept_en']??'',$it['title_en']??'',
        (float)$it['basic_salary'],(float)$it['total_allowances'],(float)$it['gross_salary'],
        (float)$it['overtime_amount'],(float)$it['total_deductions'],
        (float)$it['social_insurance'],(float)$it['tax_amount'],(float)$it['net_salary'],
        $it['payment_status']];
}
$totRow = ['','','','','','TOTALS',
    array_sum(array_column($items,'total_allowances')),
    array_sum(array_column($items,'gross_salary')),
    array_sum(array_column($items,'overtime_amount')),
    array_sum(array_column($items,'total_deductions')),
    array_sum(array_column($items,'social_insurance')),
    array_sum(array_column($items,'tax_amount')),
    array_sum(array_column($items,'net_salary')),''];
$rows1[] = $totRow;

// Build Sheet 2: Bank Transfer
$headers2 = ['#','Employee No.','Full Name','Bank Name','IBAN','Net Salary ('.$cur.')'];
$rows2 = [$headers2];
foreach ($items as $i=>$it) {
    $rows2[] = [$i+1,$it['employee_no'],$it['name_en'],$it['bank_name']??'',$it['iban']??'',(float)$it['net_salary']];
}

$xlsx = buildXlsx([
    ['name'=>'Payroll Summary','rows'=>$rows1,'total_row'=>count($rows1)-1],
    ['name'=>'Bank Transfer','rows'=>$rows2],
]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Payroll_'.$label.'.xlsx"');
header('Content-Length: '.strlen($xlsx));
header('Cache-Control: no-cache');
echo $xlsx;
