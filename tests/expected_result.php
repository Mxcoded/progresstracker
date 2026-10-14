<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function fail(string $msg): never {
    fwrite(STDERR, "FAIL: $msg\n");
    exit(1);
}

function ok(string $msg): void {
    echo "OK: $msg\n";
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    ok('DB connected');

    // Expected seed count from schema.sql
    $count = (int)$pdo->query('SELECT COUNT(*) FROM tracker_tasks')->fetchColumn();
    if ($count !== 25) {
        fail("expected 25 seed tasks, got $count");
    }
    ok("seed tasks count = $count");

    // Required columns
    $cols = $pdo->query('SHOW COLUMNS FROM tracker_tasks')->fetchAll();
    $names = array_column($cols, 'Field');
    foreach (['id','category','title','description','department','status','updated_at','sort_order'] as $req) {
        if (!in_array($req, $names, true)) {
            fail("missing required column: $req");
        }
    }
    ok('required columns present');

    // Allowed statuses
    $statusCol = null;
    foreach ($cols as $c) {
        if ($c['Field'] === 'status') {
            $statusCol = $c;
            break;
        }
    }
    $allowed = ['Not Started', 'In Progress', 'Blocked', 'Completed', 'Approved'];
    if (!$statusCol) {
        fail('status column missing');
    }
    if (stripos($statusCol['Type'], 'enum') !== false) {
        preg_match_all("/'([^']+)'/", $statusCol['Type'], $m);
        $vals = $m[1] ?? [];
        sort($vals);
        sort($allowed);
        if ($vals !== $allowed) {
            fail('status enum values differ from expected');
        }
    }
    ok('status enum matches expected values');

    // Ordering: sort_order asc, id asc
    $rows = $pdo->query('SELECT id, sort_order FROM tracker_tasks ORDER BY sort_order, id LIMIT 5')->fetchAll();
    for ($i = 1; $i < count($rows); $i++) {
        $prev = $rows[$i - 1];
        $cur  = $rows[$i];
        if ($cur['sort_order'] < $prev['sort_order']) {
            fail('ordering incorrect: sort_order desc');
        }
        if ($cur['sort_order'] === $prev['sort_order'] && $cur['id'] < $prev['id']) {
            fail('ordering incorrect: id desc when equal');
        }
    }
    ok('default ordering (sort_order ASC, id ASC) holds');

    // due_date valid
    $bad = $pdo->query("SELECT id, due_date FROM tracker_tasks WHERE due_date IS NOT NULL AND due_date NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")->fetchAll();
    if ($bad) {
        fail('invalid due_date values found: ' . json_encode(array_column($bad, 'id')));
    }
    ok('due_date values valid');

    echo "ALL PASSED\n";
    exit(0);
} catch (Throwable $e) {
    fail($e->getMessage());
}
