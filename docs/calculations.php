<?php
/**
 * api/calculations.php — история расчётов
 *
 * GET    /api/calculations.php              — получить историю текущего пользователя
 *        ?type=calories                     — фильтр по типу
 *        ?limit=20&offset=0                 — пагинация
 *        ?sort=created_at&dir=desc          — сортировка
 * POST   /api/calculations.php             — сохранить расчёт
 * DELETE /api/calculations.php?id=5        — удалить одну запись
 * DELETE /api/calculations.php?all=1       — очистить всю историю
 */

require_once __DIR__ . '/helpers.php';

$method = getMethod();
$pdo    = getDB();

// ── GET: получить историю ─────────────────────────────────────────────────
if ($method === 'GET') {
    $user = requireUser();

    $type   = $_GET['type']   ?? null;
    $limit  = min((int)($_GET['limit']  ?? 20), 100);
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    $sort   = in_array($_GET['sort'] ?? '', ['created_at','calc_type']) ? $_GET['sort'] : 'created_at';
    $dir    = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    $where  = 'WHERE user_id = ?';
    $params = [$user['id']];

    if ($type) {
        $where   .= ' AND calc_type = ?';
        $params[] = $type;
    }

    // Получаем записи
    $stmt = $pdo->prepare(
        "SELECT id, calc_type, result, result_label, params_json, created_at
         FROM calculations
         $where
         ORDER BY $sort $dir
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Декодируем JSON параметры для каждой записи
    foreach ($rows as &$row) {
        $row['params'] = json_decode($row['params_json'], true);
        unset($row['params_json']);
    }
    unset($row);

    // Общее количество для пагинации
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM calculations $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Список уникальных типов для фильтра
    $typesStmt = $pdo->prepare(
        'SELECT DISTINCT calc_type FROM calculations WHERE user_id = ? ORDER BY calc_type'
    );
    $typesStmt->execute([$user['id']]);
    $types = $typesStmt->fetchAll(PDO::FETCH_COLUMN);

    jsonSuccess([
        'items'  => $rows,
        'total'  => $total,
        'types'  => $types,
        'limit'  => $limit,
        'offset' => $offset,
    ]);
}

// ── POST: сохранить расчёт ────────────────────────────────────────────────
if ($method === 'POST') {
    $user = requireUser();
    $body = getBody();

    $calcType   = clean($body['calc_type']    ?? '');
    $params     = $body['params']             ?? [];
    $result     = clean($body['result']       ?? '');
    $resultLabel = clean($body['result_label'] ?? '');

    // Валидация
    $allowed = ['calories','bju','bmr','deficit','water','imt','bodyfat','whr',
                'ideal_weight','leanbody','anthro','pulse','maxpulse','bio_age',
                'pro_karvonen','pro_rufye','pro_ortostatic','pro_martine',
                'pro_mifflin_pro','pro_bju_pro','pro_whr_pro','pro_weight_loss','pro_zones5'];

    if (!in_array($calcType, $allowed)) jsonError('Неизвестный тип калькулятора');
    if (!$result) jsonError('Результат не передан');

    $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);

    // Ограничение: не более 100 записей на пользователя
    // Удаляем старые если превысили лимит
    $count = (int)$pdo->prepare('SELECT COUNT(*) FROM calculations WHERE user_id = ?')
                      ->execute([$user['id']]) && 1;
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM calculations WHERE user_id = ?');
    $countStmt->execute([$user['id']]);
    $count = (int)$countStmt->fetchColumn();

    if ($count >= 100) {
        // Удаляем самую старую запись
        $pdo->prepare(
            'DELETE FROM calculations WHERE user_id = ?
             ORDER BY created_at ASC LIMIT 1'
        )->execute([$user['id']]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO calculations (user_id, calc_type, params_json, result, result_label)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$user['id'], $calcType, $paramsJson, $result, $resultLabel]);
    $id = $pdo->lastInsertId();

    jsonSuccess(['id' => (int)$id, 'message' => 'Сохранено'], 201);
}

// ── DELETE: удалить запись(и) ─────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireUser();

    // Удалить всю историю
    if ($_GET['all'] ?? false) {
        $pdo->prepare('DELETE FROM calculations WHERE user_id = ?')
            ->execute([$user['id']]);
        jsonSuccess(['message' => 'История очищена']);
    }

    // Удалить одну запись
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('Не указан id записи');

    // Проверяем что запись принадлежит этому пользователю (защита от IDOR)
    $check = $pdo->prepare('SELECT id FROM calculations WHERE id = ? AND user_id = ?');
    $check->execute([$id, $user['id']]);
    if (!$check->fetch()) jsonError('Запись не найдена', 404);

    $pdo->prepare('DELETE FROM calculations WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Запись удалена']);
}
