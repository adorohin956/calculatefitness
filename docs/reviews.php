<?php
/**
 * api/reviews.php — отзывы на калькуляторы
 *
 * GET    /api/reviews.php?calc_type=calories  — отзывы на калькулятор + средний рейтинг
 * GET    /api/reviews.php?summary=1           — рейтинги всех калькуляторов
 * POST   /api/reviews.php                     — добавить/обновить отзыв
 * DELETE /api/reviews.php?id=5               — удалить свой отзыв
 */

require_once __DIR__ . '/helpers.php';

$method = getMethod();
$pdo    = getDB();

// ── GET ───────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    // Сводка по всем калькуляторам (для главной / страницы статистики)
    if ($_GET['summary'] ?? false) {
        $stmt = $pdo->query(
            "SELECT
                calc_type,
                ROUND(AVG(rating), 1)  AS avg_rating,
                COUNT(*)               AS total_reviews
             FROM reviews
             WHERE is_approved = 1
             GROUP BY calc_type
             ORDER BY avg_rating DESC"
        );
        jsonSuccess($stmt->fetchAll());
    }

    $calcType = clean($_GET['calc_type'] ?? '');
    if (!$calcType) jsonError('Не указан calc_type');

    $limit  = min((int)($_GET['limit']  ?? 10), 50);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    // Одобренные отзывы с комментариями
    $stmt = $pdo->prepare(
        "SELECT r.id, u.nickname, r.rating, r.comment, r.created_at
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.calc_type = ? AND r.is_approved = 1 AND r.comment IS NOT NULL
         ORDER BY r.created_at DESC
         LIMIT $limit OFFSET $offset"
    );
    $stmt->execute([$calcType]);
    $reviews = $stmt->fetchAll();

    // Средний рейтинг и количество
    $statsStmt = $pdo->prepare(
        "SELECT
            ROUND(AVG(rating), 1) AS avg_rating,
            COUNT(*)              AS total,
            SUM(rating = 5)       AS five,
            SUM(rating = 4)       AS four,
            SUM(rating = 3)       AS three,
            SUM(rating = 2)       AS two,
            SUM(rating = 1)       AS one
         FROM reviews
         WHERE calc_type = ? AND is_approved = 1"
    );
    $statsStmt->execute([$calcType]);
    $stats = $statsStmt->fetch();

    // Оценка текущего пользователя
    $myRating = null;
    $user = getCurrentUser();
    if ($user) {
        $myStmt = $pdo->prepare(
            'SELECT rating, comment FROM reviews WHERE user_id = ? AND calc_type = ?'
        );
        $myStmt->execute([$user['id'], $calcType]);
        $myRating = $myStmt->fetch() ?: null;
    }

    jsonSuccess([
        'reviews'   => $reviews,
        'stats'     => $stats,
        'my_rating' => $myRating,
    ]);
}

// ── POST: добавить или обновить отзыв ─────────────────────────────────────
if ($method === 'POST') {
    $user = requireUser();
    $body = getBody();

    $calcType = clean($body['calc_type'] ?? '');
    $rating   = (int)($body['rating']    ?? 0);
    $comment  = isset($body['comment']) ? clean(substr($body['comment'], 0, 500)) : null;

    $allowed = ['calories','bju','bmr','deficit','water','imt','bodyfat','whr',
                'ideal_weight','leanbody','anthro','pulse','maxpulse','bio_age',
                'pro_karvonen','pro_rufye','pro_ortostatic','pro_martine',
                'pro_mifflin_pro','pro_bju_pro','pro_weight_loss','pro_zones5'];

    if (!in_array($calcType, $allowed)) jsonError('Неизвестный калькулятор');
    if ($rating < 1 || $rating > 5) jsonError('Оценка должна быть от 1 до 5');

    // UPSERT — обновляем если уже голосовал
    $stmt = $pdo->prepare(
        'INSERT INTO reviews (user_id, calc_type, rating, comment, is_approved)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
             rating      = VALUES(rating),
             comment     = VALUES(comment),
             is_approved = 1,
             created_at  = NOW()'
    );
    $stmt->execute([$user['id'], $calcType, $rating, $comment]);

    jsonSuccess(['message' => 'Оценка сохранена'], 201);
}

// ── DELETE: удалить свой отзыв ────────────────────────────────────────────
if ($method === 'DELETE') {
    $user = requireUser();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('Не указан id');

    $check = $pdo->prepare('SELECT id FROM reviews WHERE id = ? AND user_id = ?');
    $check->execute([$id, $user['id']]);
    if (!$check->fetch()) jsonError('Отзыв не найден', 404);

    $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    jsonSuccess(['message' => 'Отзыв удалён']);
}
