<?php
/**
 * api/measurements.php — дневник замеров
 *
 * GET    /api/measurements.php              — все замеры пользователя
 *        ?from=2025-01-01&to=2025-12-31     — фильтр по периоду
 *        ?limit=30                          — ограничение
 * POST   /api/measurements.php             — добавить замер
 * PUT    /api/measurements.php?id=5        — обновить замер
 * DELETE /api/measurements.php?id=5        — удалить замер
 */

require_once __DIR__ . '/helpers.php';

$method = getMethod();
$pdo    = getDB();

// ── GET: получить замеры ──────────────────────────────────────────────────
if ($method === 'GET') {
    $user  = requireUser();
    $from  = $_GET['from']  ?? null;
    $to    = $_GET['to']    ?? null;
    $limit = min((int)($_GET['limit'] ?? 90), 365);

    $where  = 'WHERE user_id = ?';
    $params = [$user['id']];

    if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where   .= ' AND measured_at >= ?';
        $params[] = $from;
    }
    if ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where   .= ' AND measured_at <= ?';
        $params[] = $to;
    }

    $stmt = $pdo->prepare(
        "SELECT id, weight, body_fat, waist, hips, chest, notes, measured_at
         FROM measurements
         $where
         ORDER BY measured_at ASC
         LIMIT $limit"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Статистика для дашборда
    $statsStmt = $pdo->prepare(
        "SELECT
            COUNT(*)              AS total,
            MIN(weight)           AS weight_min,
            MAX(weight)           AS weight_max,
            ROUND(AVG(weight), 1) AS weight_avg,
            MIN(body_fat)         AS fat_min,
            MAX(body_fat)         AS fat_max,
            MIN(measured_at)      AS first_date,
            MAX(measured_at)      AS last_date
         FROM measurements $where"
    );
    $statsStmt->execute($params);
    $stats = $statsStmt->fetch();

    // Изменение веса (первый vs последний)
    $stats['weight_change'] = null;
    if (count($rows) >= 2) {
        $first = null;
        $last  = null;
        foreach ($rows as $r) {
            if ($r['weight'] !== null) {
                if ($first === null) $first = (float)$r['weight'];
                $last = (float)$r['weight'];
            }
        }
        if ($first !== null && $last !== null) {
            $stats['weight_change'] = round($last - $first, 1);
        }
    }

    jsonSuccess([
        'items' => $rows,
        'stats' => $stats,
    ]);
}

// ── POST: добавить замер ──────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireUser();
    $body = getBody();

    $date  = $body['measured_at'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
        jsonError('Некорректная дата (ожидается YYYY-MM-DD)');

    // Хотя бы одно числовое поле должно быть заполнено
    $numFields = ['weight','body_fat','waist','hips','chest'];
    $hasValue  = false;
    $values    = [];

    foreach ($numFields as $f) {
        if (isset($body[$f]) && $body[$f] !== '' && $body[$f] !== null) {
            $val = (float)$body[$f];
            if ($val <= 0 || $val > 500) jsonError("Некорректное значение для $f");
            $values[$f] = $val;
            $hasValue   = true;
        } else {
            $values[$f] = null;
        }
    }

    if (!$hasValue) jsonError('Заполните хотя бы одно поле');

    $notes = isset($body['notes']) ? clean(substr($body['notes'], 0, 500)) : null;

    $stmt = $pdo->prepare(
        'INSERT INTO measurements (user_id, weight, body_fat, waist, hips, chest, notes, measured_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'],
        $values['weight'], $values['body_fat'],
        $values['waist'],  $values['hips'], $values['chest'],
        $notes, $date
    ]);

    jsonSuccess(['id' => (int)$pdo->lastInsertId(), 'message' => 'Замер добавлен'], 201);
}

// ── PUT: обновить замер ───────────────────────────────────────────────────
if ($method === 'PUT') {
    $user = requireUser();
    $id   = (int)($_GET['id'] ?? 0);
    $body = getBody();

    if (!$id) jsonError('Не указан id записи');

    // Проверяем владельца
    $check = $pdo->prepare('SELECT id FROM measurements WHERE id = ? AND user_id = ?');
    $check->execute([$id, $user['id']]);
    if (!$check->fetch()) jsonError('Запись не найдена', 404);

    $fields = [];
    $params = [];
    $numFields = ['weight','body_fat','waist','hips','chest'];

    foreach ($numFields as $f) {
        if (array_key_exists($f, $body)) {
            $val = $body[$f] === null || $body[$f] === '' ? null : (float)$body[$f];
            $fields[] = "$f = ?";
            $params[] = $val;
        }
    }
    if (array_key_exists('notes', $body)) {
        $fields[] = 'notes = ?';
        $params[] = $body['notes'] ? clean(substr($body['notes'], 0, 500)) : null;
    }
    if (array_key_exists('measured_at', $body)) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $body['measured_at']))
            jsonError('Некорректная дата');
        $fields[] = 'measured_at = ?';
        $params[] = $body['measured_at'];
    }

    if (empty($fields)) jsonError('Нечего обновлять');

    $params[] = $id;
    $pdo->prepare('UPDATE measurements SET ' . implode(', ', $fields) . ' WHERE id = ?')
        ->execute($params);

    jsonSuccess(['message' => 'Замер обновлён']);
}

// ── DELETE: удалить замер ─────────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireUser();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('Не указан id записи');

    $check = $pdo->prepare('SELECT id FROM measurements WHERE id = ? AND user_id = ?');
    $check->execute([$id, $user['id']]);
    if (!$check->fetch()) jsonError('Запись не найдена', 404);

    $pdo->prepare('DELETE FROM measurements WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Замер удалён']);
}
