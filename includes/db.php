<?php
class DB {
    private static ?PDO $instance = null;

    public static function conn(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
        return self::$instance;
    }

    public static function q(string $sql, array $params = []): PDOStatement {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function row(string $sql, array $params = []): ?array {
        return self::q($sql, $params)->fetch() ?: null;
    }

    public static function rows(string $sql, array $params = []): array {
        return self::q($sql, $params)->fetchAll();
    }

    public static function val(string $sql, array $params = []): mixed {
        $r = self::q($sql, $params)->fetch(PDO::FETCH_NUM);
        return $r ? $r[0] : null;
    }

    public static function insert(string $table, array $data): int {
        $cols = implode(',', array_map(fn($k) => "`$k`", array_keys($data)));
        $plhs = implode(',', array_fill(0, count($data), '?'));
        self::q("INSERT INTO `$table` ($cols) VALUES ($plhs)", array_values($data));
        return (int) self::conn()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $wParams = []): int {
        $set = implode(',', array_map(fn($k) => "`$k`=?", array_keys($data)));
        $stmt = self::q("UPDATE `$table` SET $set WHERE $where", [...array_values($data), ...$wParams]);
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int {
        return self::q("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }

    public static function paginate(string $sql, array $params, int $page, int $perPage = PER_PAGE): array {
        $total = (int) self::val("SELECT COUNT(*) FROM ($sql) t", $params);
        $offset = ($page - 1) * $perPage;
        $rows = self::rows("$sql LIMIT $perPage OFFSET $offset", $params);
        return [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => (int) ceil($total / $perPage),
            'from'       => $offset + 1,
            'to'         => min($offset + $perPage, $total),
        ];
    }
}
