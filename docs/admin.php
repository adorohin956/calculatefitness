<?php
/**
 * api/admin.php — серверная часть административной панели
 *
 * GET ?section=dashboard    — общая статистика
 * GET ?section=reviews      — все отзывы (с фильтром)
 * PUT ?section=reviews&id=N — одобрить/скрыть отзыв
 * DEL ?section=reviews&id=N — удалить отзыв
 * GET ?section=users        — список пользователей
 * DEL ?section=users&id=N   — удалить пользователя
 * GET ?section=calcs_stats  — статистика расчётов
 */

require_once __DIR__ . '/helpers.php';

requireAdmin();  // Все методы только для admin

$method  = getMethod();
$section = $_GET['section'] ?? '';
$pdo     = getDB();

// ── DASHBOARD ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $section === 'dashboard') {

    $stats = [];

    $stats['total_users'] = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['total_calcs'] = (int)$pdo->query('SELECT COUNT(*) FROM calculations')->fetchColumn();
    $stats['calcs_today'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM calculations WHERE DATE(created_at) = CURDATE()"
    )->fetchColumn();
    $stats['new_contacts'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM contacts WHERE status = 'new'"
    )->fetchColumn();
    $stats['total_reviews']    = (int)$pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
    $stats['approved_reviews'] = (int)$pdo->query('SELECT COUNT(*) FROM reviews WHERE is_approved=1')->fetchColumn();

    // Топ калькуляторов
    $stmt = $pdo->query(
        "SELECT calc_type, COUNT(*) AS cnt
         FROM calculations
         GROUP BY calc_type
         ORDER BY cnt DESC
         LIMIT 10"
    );
    $stats['top_calcs'] = $stmt->fetchAll();

    // Активность по дням (последние 7 дней)
    $stmt = $pdo->query(
        "SELECT DATE(created_at) AS day, COUNT(*) AS cnt
         FROM calculations
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day"
    );
    $stats['activity_week'] = $stmt->fetchAll();

    jsonSuccess($stats);
}

// ── REVIEWS ───────────────────────────────────────────────────────────────
if ($method === 'GET' && $section === 'reviews') {
    $calcType = $_GET['calc_type'] ?? null;
    $limit    = min((int)($_GET['limit'] ?? 50), 200);

    $where  = 'WHERE 1=1';
    $params = [];
    if ($calcType) { $where .= ' AND r.calc_type = ?'; $params[] = $calcType; }

    $stmt = $pdo->prepare(
        "SELECT r.id, r.calc_type, r.rating, r.comment, r.is_approved, r.created_at,
                u.nickname
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         $where
         ORDER BY r.created_at DESC
         LIMIT $limit"
    );
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

if ($method === 'PUT' && $section === 'reviews') {
    $id   = (int)($_GET['id'] ?? 0);
    $body = getBody();
    if (!$id) jsonError('Не указан id');
    $approved = isset($body['is_approved']) ? (int)$body['is_approved'] : null;
    if ($approved === null) jsonError('Не указан is_approved');
    $pdo->prepare('UPDATE reviews SET is_approved = ? WHERE id = ?')->execute([$approved, $id]);
    jsonSuccess(['message' => 'Обновлено']);
}

if ($method === 'DELETE' && $section === 'reviews') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('Не указан id');
    $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Отзыв удалён']);
}

// ── USERS ─────────────────────────────────────────────────────────────────
if ($method === 'GET' && $section === 'users') {
    $search = clean($_GET['search'] ?? '');
    $limit  = min((int)($_GET['limit'] ?? 50), 200);

    $where  = 'WHERE 1=1';
    $params = [];
    if ($search) {
        $where   .= ' AND (u.nickname LIKE ? OR u.email LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $stmt = $pdo->prepare(
        "SELECT u.id, u.nickname, u.email, u.role, u.created_at,
                COUNT(c.id) AS calc_count
         FROM users u
         LEFT JOIN calculations c ON c.user_id = u.id
         $where
         GROUP BY u.id
         ORDER BY u.created_at DESC
         LIMIT $limit"
    );
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $countStmt->execute($params);

    jsonSuccess([
        'items' => $items,
        'total' => (int)$countStmt->fetchColumn(),
    ]);
}

if ($method === 'DELETE' && $section === 'users') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('Не указан id');
    // Защита от удаления самого себя
    $admin = requireAdmin();
    if ($admin['id'] === $id) jsonError('Нельзя удалить самого себя');
    // CASCADE удалит все связанные данные
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Пользователь удалён']);
}

// ── CALCS STATS ───────────────────────────────────────────────────────────
if ($method === 'GET' && $section === 'calcs_stats') {
    $stmt = $pdo->query(
        "SELECT
            calc_type,
            COUNT(*)                    AS total,
            COUNT(DISTINCT user_id)     AS unique_users,
            MAX(created_at)             AS last_calc,
            ROUND(AVG(
                TIMESTAMPDIFF(SECOND,
                    (SELECT MIN(c2.created_at) FROM calculations c2 WHERE c2.user_id = c.user_id),
                    created_at
                )
            ) / 86400, 1) AS avg_days_between
         FROM calculations c
         GROUP BY calc_type
         ORDER BY total DESC"
    );
    jsonSuccess($stmt->fetchAll());
}

jsonError('Неизвестный раздел', 404);
