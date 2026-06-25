<?php
$endpoint = get('endpoint');
$result = [];
switch ($endpoint) {
    case 'employees':
        $q = get('q');
        $status = get('status', 'active');
        $allowedStatus = ['active','terminated','all'];
        if (!in_array($status, $allowedStatus)) $status = 'active';
        $statusSql = $status === 'all' ? "status IN ('active','terminated','on_leave','probation')" : "status=?";
        $params = ["%$q%","%$q%","%$q%"];
        if ($status !== 'all') array_unshift($params, $status);
        $result = DB::rows("SELECT id, name_en, name_ar, employee_no, status FROM employees
            WHERE $statusSql AND (name_en LIKE ? OR name_ar LIKE ? OR employee_no LIKE ?)
            ORDER BY name_en LIMIT 20", $params);
        break;
    case 'dept_stats':
        $result = DB::rows("SELECT d.name_en, COUNT(e.id) as cnt, SUM(e.basic_salary) as total
            FROM departments d LEFT JOIN employees e ON e.department_id=d.id AND e.status='active'
            GROUP BY d.id");
        break;
    case 'payroll_chart':
        $result = DB::rows("SELECT period_year, period_month, total_net FROM payroll_periods
            WHERE status IN ('approved','paid') ORDER BY period_year, period_month LIMIT 12");
        break;
}
echo json_encode(['data' => $result, 'success' => true]);
