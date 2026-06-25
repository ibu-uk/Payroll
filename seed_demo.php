<?php
// Seed demo employees — works via browser or CLI
// Browser: http://localhost/payroll/seed_demo.php?count=50
// CLI:     php seed_demo.php 50

define('SEEDER', true);
require_once 'config/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
startSession();

$count = (int)(isset($argv[1]) ? $argv[1] : ($_GET['count'] ?? 20));
$count = min(max($count, 1), 500);

$depts = [1,2,3,4,5,6];
$jobs  = [1,2,3,4,5,6,7,8,9,10];
$firstNames = ['Ahmed','Sara','Mohammed','Fatima','Khalid','Nora','Abdullah','Maryam','Ali','Hessa','Omar','Dalal','Yousuf','Reem','Tariq','Lulwa','Hamad','Noura','Faisal','Aisha'];
$firstNamesAr = ['أحمد','سارة','محمد','فاطمة','خالد','نورة','عبدالله','مريم','علي','حصة','عمر','دلال','يوسف','ريم','طارق','لولوة','حمد','نورا','فيصل','عائشة'];
$surnames = ['Al-Rashidi','Al-Abdullah','Al-Yousuf','Al-Hassan','Al-Mutairi','Al-Otaibi','Al-Harbi','Al-Ghamdi','Al-Zahrani','Al-Dosari'];
$surnamesAr = ['الراشدي','العبدالله','اليوسف','الحسن','المطيري','العتيبي','الحربي','الغامدي','الزهراني','الدوسري'];

$added = 0;
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    echo '<pre style="font-family:monospace;background:#1e293b;color:#f1f5f9;padding:20px;margin:20px;border-radius:10px">';
    echo "PayrollPro – Seeding $count employees...\n\n";
}

for ($i = 0; $i < $count; $i++) {
    $ni = $i % count($firstNames);
    $si = rand(0, count($surnames)-1);
    $nameEn = $firstNames[$ni] . ' ' . $surnames[$si];
    $nameAr = $firstNamesAr[$ni] . ' ' . $surnamesAr[$si];
    $basic   = round(rand(400, 2800) + rand(0,999)/1000, 3);
    $deptId  = $depts[array_rand($depts)];
    $jobId   = $jobs[array_rand($jobs)];
    $hire    = date('Y-m-d', strtotime('-' . rand(30, 2000) . ' days'));
    $gender  = in_array($firstNames[$ni], ['Sara','Fatima','Nora','Maryam','Hessa','Dalal','Reem','Lulwa','Noura','Aisha']) ? 'female' : 'male';
    try {
        $empNo = generateEmpNo();
        $empId = DB::insert('employees', [
            'employee_no'=>$empNo,'name_en'=>$nameEn,'name_ar'=>$nameAr,
            'email'=>strtolower(str_replace(' ','.', $nameEn)).'@company.com',
            'gender'=>$gender,'department_id'=>$deptId,'job_title_id'=>$jobId,
            'hire_date'=>$hire,'basic_salary'=>$basic,'status'=>'active',
            'nationality'=>'Kuwaiti',
        ]);
        DB::insert('employee_allowances',['employee_id'=>$empId,'allowance_type_id'=>1,'amount'=>round($basic*0.25,3),'is_active'=>1]);
        DB::insert('employee_allowances',['employee_id'=>$empId,'allowance_type_id'=>2,'amount'=>rand(30,80),'is_active'=>1]);
        echo "  ✓ $empNo  $nameEn\n";
        $added++;
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n✅ Done! Added $added employees.\n";
if (!$isCli) {
    echo '</pre>';
    echo '<a href="index.php" style="display:block;text-align:center;margin:20px;padding:12px;background:#3b82f6;color:#fff;border-radius:8px;text-decoration:none;font-family:sans-serif">→ Go to Dashboard</a>';
}
