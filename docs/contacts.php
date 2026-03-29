<?php
/**
 * api/contacts.php — заявки на консультацию
 *
 * POST /api/contacts.php  — отправить заявку (публичный)
 * GET  /api/contacts.php  — список заявок (только admin)
 *      ?status=new        — фильтр по статусу
 *      ?sort=created_at   — сортировка
 * PUT  /api/contacts.php?id=5  — сменить статус (только admin)
 * DELETE /api/contacts.php?id=5 — удалить заявку (только admin)
 */

require_once __DIR__ . '/helpers.php';

$method = getMethod();
$pdo    = getDB();

// ── POST: отправить заявку (публичный эндпоинт) ───────────────────────────
if ($method === 'POST') {
    $body = getBody();

    $name    = clean($body['name']    ?? '');
    $phone   = clean($body['phone']   ?? '');
    $goal    = clean($body['goal']    ?? 'other');
    $message = clean(substr($body['message'] ?? '', 0, 1000));

    if (strlen($name) < 2) jsonError('Введите имя (минимум 2 символа)');

    $validGoals = ['lose','gain','health','sport','other'];
    if (!in_array($goal, $validGoals)) $goal = 'other';

    // Пользователь может быть гостем
    $user   = getCurrentUser();
    $userId = $user ? $user['id'] : null;

    $stmt = $pdo->prepare(
        'INSERT INTO contacts (user_id, name, phone, goal, message)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $name, $phone ?: null, $goal, $message ?: null]);

    jsonSuccess(['message' => 'Заявка отправлена! Тренер свяжется с вами.'], 201);
}

// ── GET: список заявок (только admin) ─────────────────────────────────────
if ($method === 'GET') {
    requireAdmin();

    $status = $_GET['status'] ?? null;
    $goal   = $_GET['goal']   ?? null;
    $sort   = in_array($_GET['sort'] ?? '', ['created_at','status','name']) ? $_GET['sort'] : 'created_at';
    $dir    = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
    $limit  = min((int)($_GET['limit']  ?? 20), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $where  = 'WHERE 1=1';
    $params = [];

    if ($status) {
        $where   .= ' AND c.status = ?';
        $params[] = $status;
    }
    if ($goal) {
        $where   .= ' AND c.goal = ?';
        $params[] = $goal;
    }

    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.phone, c.goal, c.message, c.status, c.created_at,
                u.nickname
         FROM contacts c
         LEFT JOIN users u ON u.id = c.user_id
         $where
         ORDER BY c.$sort $dir
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Счётчики по статусам
    $countsStmt = $pdo->query(
        "SELECT status, COUNT(*) as cnt FROM contacts GROUP BY status"
    );
    $counts = [];
    foreach ($countsStmt->fetchAll() as $row) {
        $counts[$row['status']] = (int)$row['cnt'];
    }

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM contacts $where");
    $totalStmt->execute($params);

    jsonSuccess([
        'items'   => $rows,
        'total'   => (int)$totalStmt->fetchColumn(),
        'counts'  => $counts,
        'limit'   => $limit,
        'offset'  => $offset,
    ]);
}

// ── PUT: обновить статус (только admin) ───────────────────────────────────
if ($method === 'PUT') {
    requireAdmin();

    $id     = (int)($_GET['id'] ?? 0);
    $body   = getBody();
    $status = $body['status'] ?? '';

    if (!$id) jsonError('Не указан id');
    $validStatuses = ['new','processing','done'];
    if (!in_array($status, $validStatuses)) jsonError('Недопустимый статус');

    $check = $pdo->prepare('SELECT id FROM contacts WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) jsonError('Заявка не найдена', 404);

    $pdo->prepare('UPDATE contacts SET status = ? WHERE id = ?')
        ->execute([$status, $id]);

    jsonSuccess(['message' => 'Статус обновлён']);
}

// ── DELETE: удалить заявку (только admin) ─────────────────────────────────
if ($method === 'DELETE') {
    requireAdmin();

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('Не указан id');

    $check = $pdo->prepare('SELECT id FROM contacts WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) jsonError('Заявка не найдена', 404);

    $pdo->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Заявка удалена']);
}
