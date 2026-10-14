<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require __DIR__ . '/config.php';

function respond(array $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function input_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function require_login(): void {
    if (empty($_SESSION['tracker_auth'])) respond(['ok'=>false,'error'=>'Please enter the access code again.'], 401);
}
function clean_text($value, int $max=3000): string {
    $s = trim((string)($value ?? ''));
    return mb_substr($s, 0, $max);
}
try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    $action = $_GET['action'] ?? '';
    $data = input_json();

    if ($action === 'login') {
        $code = (string)($data['code'] ?? '');
        $name = clean_text($data['name'] ?? '', 80);
        if ($name === '') respond(['ok'=>false,'error'=>'Enter your name or department.'], 422);
        if (!hash_equals(TRACKER_ACCESS_CODE, $code)) {
            usleep(350000);
            respond(['ok'=>false,'error'=>'Invalid access code. Please check with the IT Manager.'], 403);
        }
        session_regenerate_id(true);
        $_SESSION['tracker_auth'] = true;
        $_SESSION['tracker_name'] = $name;
        respond(['ok'=>true,'name'=>$name]);
    }
    if ($action === 'session') {
        respond(['ok'=>true,'authenticated'=>!empty($_SESSION['tracker_auth']),'name'=>$_SESSION['tracker_name'] ?? '']);
    }
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p=session_get_cookie_params();
            setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        respond(['ok'=>true]);
    }
    require_login();

    if ($action === 'list') {
        $rows = $pdo->query('SELECT id, category, title, description, department, owner, due_date, status, notes, updated_by, updated_at FROM tracker_tasks ORDER BY sort_order, id')->fetchAll();
        $updated = $pdo->query('SELECT MAX(updated_at) AS updated_at FROM tracker_tasks')->fetchColumn();
        respond(['ok'=>true,'tasks'=>$rows,'updated_at'=>$updated ?: null]);
    }
    if ($action === 'update') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) respond(['ok'=>false,'error'=>'Invalid task ID.'], 422);
        $status = clean_text($data['status'] ?? '', 20);
        $allowed = ['Not Started','In Progress','Blocked','Completed','Approved'];
        if (!in_array($status, $allowed, true)) respond(['ok'=>false,'error'=>'Invalid status selected.'], 422);
        $owner = clean_text($data['owner'] ?? '', 100);
        $notes = clean_text($data['notes'] ?? '', 3000);
        $due = clean_text($data['due_date'] ?? '', 10);
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) respond(['ok'=>false,'error'=>'Invalid due date.'], 422);
        $stmt = $pdo->prepare('UPDATE tracker_tasks SET status=?, owner=?, due_date=?, notes=?, updated_by=?, updated_at=UTC_TIMESTAMP() WHERE id=?');
        $stmt->execute([$status,$owner,$due ?: null,$notes,clean_text($_SESSION['tracker_name'] ?? 'User',80),$id]);
        if ($stmt->rowCount() === 0) {
            $check=$pdo->prepare('SELECT id FROM tracker_tasks WHERE id=?');$check->execute([$id]);
            if (!$check->fetch()) respond(['ok'=>false,'error'=>'Task not found.'],404);
        }
        respond(['ok'=>true]);
    }
    respond(['ok'=>false,'error'=>'Unknown action.'], 404);
} catch (Throwable $e) {
    error_log('Valino tracker error: '.$e->getMessage());
    respond(['ok'=>false,'error'=>'Server/database error. Check the tracker setup or contact the IT Manager.'], 500);
}
?>