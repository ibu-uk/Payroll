<?php
// Pure PHP payslip - opens as print-ready HTML (no Composer needed)
// User prints to PDF via browser Ctrl+P → Save as PDF

$periodId = (int)($_GET['period_id'] ?? 0);
$itemId   = (int)($_GET['item_id']   ?? 0);
$empId    = (int)($_GET['emp_id']    ?? 0);
$year     = (int)($_GET['year']      ?? 0);
$month    = (int)($_GET['month']     ?? 0);

if ($itemId > 0) {
    $item = DB::row("SELECT * FROM payroll_items WHERE id=?", [$itemId]);
} elseif ($empId > 0 && $periodId > 0) {
    $item = DB::row("SELECT * FROM payroll_items WHERE employee_id=? AND payroll_period_id=?", [$empId, $periodId]);
} elseif ($empId > 0 && $year > 0 && $month > 0) {
    $period = DB::row("SELECT * FROM payroll_periods WHERE period_year=? AND period_month=?", [$year, $month]);
    if ($period) {
        $item = DB::row("SELECT * FROM payroll_items WHERE employee_id=? AND payroll_period_id=?", [$empId, $period['id']]);
    } else {
        $item = null;
    }
} elseif ($periodId > 0) {
    // All payslips for period — print all
    $items = DB::rows("SELECT pi.id FROM payroll_items pi WHERE pi.payroll_period_id=?", [$periodId]);
    if (count($items) > 1) {
        $html = '';
        foreach ($items as $i) {
            ob_start();
            $_GET['item_id'] = $i['id'];
            $item2 = DB::row("SELECT * FROM payroll_items WHERE id=?", [$i['id']]);
            if (!$item2) continue;
            $html .= buildPayslipHTML($item2);
            $html .= '<div style="page-break-after:always"></div>';
        }
        outputPayslipPage($html);
        exit;
    }
    $item = DB::row("SELECT * FROM payroll_items WHERE payroll_period_id=? LIMIT 1", [$periodId]);
} else {
    die('Invalid parameters');
}
if (!$item) die('Payroll record not found');

outputPayslipPage(buildPayslipHTML($item));

function buildPayslipHTML(array $item): string {
    $emp    = DB::row("SELECT e.*,d.name_en dept_en,d.name_ar dept_ar,j.title_en,j.title_ar FROM employees e LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN job_titles j ON j.id=e.job_title_id WHERE e.id=?", [$item['employee_id']]);
    $period = DB::row("SELECT * FROM payroll_periods WHERE id=?", [$item['payroll_period_id']]);
    $s      = DB::row("SELECT * FROM settings WHERE id=1") ?? [];
    $details= DB::rows("SELECT * FROM payroll_item_details WHERE payroll_item_id=?", [$item['id']]);
    $allows = array_filter($details, fn($d)=>$d['item_type']==='allowance');
    $deds   = array_filter($details, fn($d)=>$d['item_type']==='deduction');
    $cur    = $s['currency'] ?? 'KWD';
    $logo   = '';
    if (!empty($s['logo'])) {
        $logoPath = APP_DIR . '/uploads/logos/' . $s['logo'];
        if (file_exists($logoPath)) {
            $type = pathinfo($logoPath, PATHINFO_EXTENSION);
            $data = base64_encode(file_get_contents($logoPath));
            $logo = "<img src=\"data:image/{$type};base64,{$data}\" style='height:50px;display:block;margin-bottom:4px'>";
        }
    }
    $fmt = fn($v) => number_format((float)$v, 3);
    ob_start(); ?>
<div class="payslip">
<div class="ps-header">
  <div class="ps-company">
    <?= $logo ?>
    <div class="ps-company-name"><?= htmlspecialchars($s['company_name_en'] ?? 'PayrollPro') ?></div>
    <div class="ps-company-addr"><?= htmlspecialchars($s['company_address_en'] ?? '') ?></div>
  </div>
  <div class="ps-title-block">
    <div class="ps-company-name-ar"><?= htmlspecialchars($s['company_name_ar'] ?? '') ?></div>
    <div class="ps-title">PAYSLIP / قسيمة الراتب</div>
    <div class="ps-period"><?= htmlspecialchars($period['period_label'] ?? '') ?></div>
    <div class="ps-status"><?= strtoupper($period['status'] ?? '') ?></div>
  </div>
</div>
<div class="ps-info-grid">
  <div class="ps-info-box"><div class="ps-info-label">Employee / الموظف</div><div class="ps-info-val"><?= htmlspecialchars($emp['name_en']) ?></div><div class="ps-info-ar"><?= htmlspecialchars($emp['name_ar'] ?? '') ?></div></div>
  <div class="ps-info-box"><div class="ps-info-label">Employee No. / رقم الموظف</div><div class="ps-info-val"><?= htmlspecialchars($emp['employee_no']) ?></div></div>
  <div class="ps-info-box"><div class="ps-info-label">Department / القسم</div><div class="ps-info-val"><?= htmlspecialchars($emp['dept_en'] ?? '-') ?></div><div class="ps-info-ar"><?= htmlspecialchars($emp['dept_ar'] ?? '') ?></div></div>
  <div class="ps-info-box"><div class="ps-info-label">Job Title / المسمى الوظيفي</div><div class="ps-info-val"><?= htmlspecialchars($emp['title_en'] ?? '-') ?></div><div class="ps-info-ar"><?= htmlspecialchars($emp['title_ar'] ?? '') ?></div></div>
  <div class="ps-info-box"><div class="ps-info-label">Pay Period / فترة الراتب</div><div class="ps-info-val"><?= date('d/m/Y', strtotime($period['start_date'])) ?> – <?= date('d/m/Y', strtotime($period['end_date'])) ?></div></div>
  <div class="ps-info-box"><div class="ps-info-label">Payment Date / تاريخ الصرف</div><div class="ps-info-val"><?= $period['payment_date'] ? date('d/m/Y', strtotime($period['payment_date'])) : '-' ?></div></div>
</div>
<table class="ps-table">
  <thead><tr><th>Earnings / المستحقات</th><th dir="rtl">العنصر</th><th class="ps-amt">Amount (<?= $cur ?>)</th></tr></thead>
  <tbody>
    <tr><td>Basic Salary / الراتب الأساسي</td><td dir="rtl">راتب أساسي</td><td class="ps-amt"><?= $fmt($item['basic_salary']) ?></td></tr>
    <?php foreach ($allows as $a): ?><tr><td><?= htmlspecialchars($a['name_en']) ?></td><td dir="rtl"><?= htmlspecialchars($a['name_ar'] ?? '') ?></td><td class="ps-amt"><?= $fmt($a['amount']) ?></td></tr><?php endforeach; ?>
    <?php if ($item['overtime_amount'] > 0): ?><tr><td>Overtime (<?= $item['overtime_hours'] ?> hrs) / عمل إضافي</td><td dir="rtl">عمل إضافي</td><td class="ps-amt text-blue"><?= $fmt($item['overtime_amount']) ?></td></tr><?php endif; ?>
    <tr class="ps-total-row"><td colspan="2"><strong>Gross Salary / الراتب الإجمالي</strong></td><td class="ps-amt"><?= $fmt((float)$item['gross_salary'] + (float)$item['overtime_amount']) ?></td></tr>
  </tbody>
</table>
<table class="ps-table">
  <thead><tr><th>Deductions / الخصومات</th><th dir="rtl">العنصر</th><th class="ps-amt">Amount (<?= $cur ?>)</th></tr></thead>
  <tbody>
    <?php if ($item['social_insurance'] > 0): ?><tr><td>Social Insurance / تأمين اجتماعي</td><td dir="rtl">تأمين اجتماعي</td><td class="ps-amt text-red"><?= $fmt($item['social_insurance']) ?></td></tr><?php endif; ?>
    <?php if ($item['tax_amount'] > 0): ?><tr><td>Income Tax / ضريبة الدخل</td><td dir="rtl">ضريبة</td><td class="ps-amt text-red"><?= $fmt($item['tax_amount']) ?></td></tr><?php endif; ?>
    <?php if ($item['absent_deduction'] > 0): ?><tr><td>Absence (<?= $item['absent_days'] ?> days) / غياب</td><td dir="rtl">غياب</td><td class="ps-amt text-red"><?= $fmt($item['absent_deduction']) ?></td></tr><?php endif; ?>
    <?php if ($item['late_deduction'] > 0): ?><tr><td>Late (<?= $item['late_minutes'] ?> min) / تأخر</td><td dir="rtl">تأخر</td><td class="ps-amt text-red"><?= $fmt($item['late_deduction']) ?></td></tr><?php endif; ?>
    <?php if ($item['loan_deduction'] > 0): ?><tr><td>Loan Deduction / خصم قرض</td><td dir="rtl">قرض</td><td class="ps-amt text-red"><?= $fmt($item['loan_deduction']) ?></td></tr><?php endif; ?>
    <?php foreach ($deds as $d): ?><tr><td><?= htmlspecialchars($d['name_en']) ?></td><td dir="rtl"><?= htmlspecialchars($d['name_ar'] ?? '') ?></td><td class="ps-amt text-red"><?= $fmt($d['amount']) ?></td></tr><?php endforeach; ?>
    <tr class="ps-deduct-row"><td colspan="2"><strong>Total Deductions / إجمالي الخصومات</strong></td><td class="ps-amt"><?= $fmt($item['total_deductions']) ?></td></tr>
  </tbody>
</table>
<div class="ps-net-box">
  <div class="ps-net-label">NET SALARY / صافي الراتب</div>
  <div class="ps-net-amount"><?= $fmt($item['net_salary']) ?> <?= htmlspecialchars($cur) ?></div>
</div>
<div class="ps-sig-row">
  <div class="ps-sig-box"><div class="ps-sig-line">Employee Signature<br>توقيع الموظف</div></div>
  <div class="ps-sig-box"><div class="ps-sig-line">HR Manager<br>مدير الموارد البشرية</div></div>
  <div class="ps-sig-box"><div class="ps-sig-line">Finance Director<br>مدير المالية</div></div>
</div>
<div class="ps-footer"><?= htmlspecialchars($s['company_name_en'] ?? '') ?> &nbsp;|&nbsp; Generated: <?= date('d/m/Y H:i') ?> &nbsp;|&nbsp; Computer-generated / وثيقة رسمية صادرة من النظام</div>
</div>
    <?php return ob_get_clean();
}

function outputPayslipPage(string $body): void {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Payslip</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:"Inter","Cairo",sans-serif;font-size:10pt;color:#1e293b;background:#f1f5f9;padding:20px}
.payslip{background:#fff;max-width:800px;margin:0 auto 30px;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1);padding:24px}
.ps-header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #3b82f6;padding-bottom:14px;margin-bottom:16px}
.ps-company-name{font-size:16pt;font-weight:700;color:#1e293b}
.ps-company-addr{font-size:8pt;color:#64748b}
.ps-company-name-ar{font-size:12pt;font-weight:700;color:#1e293b;text-align:right;font-family:"Cairo",sans-serif}
.ps-title{font-size:13pt;font-weight:700;color:#3b82f6;text-align:right}
.ps-period,.ps-status{text-align:right;font-size:9pt;color:#64748b}
.ps-status{background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:4px;display:inline-block;margin-top:2px}
.ps-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px}
.ps-info-box{background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:8px 10px}
.ps-info-label{font-size:8pt;color:#64748b;margin-bottom:2px}
.ps-info-val{font-size:10pt;font-weight:600;color:#1e293b}
.ps-info-ar{font-size:9pt;color:#64748b;font-family:"Cairo",sans-serif}
.ps-table{width:100%;border-collapse:collapse;margin-bottom:12px}
.ps-table thead tr{background:#1e293b;color:#fff}
.ps-table th{padding:7px 10px;text-align:left;font-size:8.5pt;font-weight:600}
.ps-table td{padding:5px 10px;border-bottom:1px solid #f1f5f9;font-size:9pt}
.ps-table tr:nth-child(even) td{background:#f8fafc}
.ps-amt{text-align:right;font-weight:600;font-family:monospace}
.ps-total-row td{background:#f0fdf4!important;font-weight:700;font-size:10pt;border-top:2px solid #10b981}
.ps-deduct-row td{background:#fff1f2!important;font-weight:700;border-top:2px solid #ef4444}
.text-red{color:#dc2626}.text-blue{color:#2563eb}
.ps-net-box{background:linear-gradient(135deg,#1e293b,#334155);color:#fff;border-radius:10px;padding:14px 20px;text-align:center;margin:12px 0}
.ps-net-label{font-size:9pt;opacity:.8;font-family:"Cairo",sans-serif}
.ps-net-amount{font-size:20pt;font-weight:800;letter-spacing:-0.5px;margin-top:4px;font-family:monospace}
.ps-sig-row{display:flex;justify-content:space-between;margin-top:20px}
.ps-sig-box{text-align:center;width:32%}
.ps-sig-line{border-top:1px solid #94a3b8;padding-top:6px;font-size:8.5pt;color:#64748b;margin-top:28px}
.ps-footer{font-size:7.5pt;color:#94a3b8;text-align:center;margin-top:14px;border-top:1px solid #e2e8f0;padding-top:8px}
.btn-print{display:block;text-align:center;margin:0 auto 20px;padding:10px 30px;background:linear-gradient(135deg,#3b82f6,#6366f1);color:#fff;border:none;border-radius:8px;font-size:13px;cursor:pointer;font-weight:600}
@media print{.btn-print,.no-print{display:none!important}body{background:#fff;padding:0}.payslip{box-shadow:none;border-radius:0;margin:0}}
</style></head><body>
<div class="no-print" style="text-align:center;margin-bottom:16px">
  <button class="btn-print" onclick="window.print()">🖨️ Print / Save as PDF</button>
</div>';
    echo $body;
    echo '</body></html>';
}
