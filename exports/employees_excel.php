<?php
// Pure PHP CSV export - opens perfectly in Excel
$employees = DB::rows("SELECT e.*,d.name_en dept_en,j.title_en FROM employees e
    LEFT JOIN departments d ON d.id=e.department_id LEFT JOIN job_titles j ON j.id=e.job_title_id
    ORDER BY e.name_en");

$filename = 'Employees_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output','w');
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel

fputcsv($out,['#','Emp No.','Name (EN)','Name (AR)','Department','Job Title','Hire Date','Nationality','Gender','Status','Basic Salary','Email','Phone','Bank','IBAN']);
foreach ($employees as $i=>$e) {
    fputcsv($out,[$i+1,$e['employee_no'],$e['name_en'],$e['name_ar']??'',$e['dept_en']??'',$e['title_en']??'',$e['hire_date'],$e['nationality']??'',$e['gender'],$e['status'],number_format((float)$e['basic_salary'],3),$e['email']??'',$e['phone']??'',$e['bank_name']??'',$e['iban']??'']);
}
fclose($out);
