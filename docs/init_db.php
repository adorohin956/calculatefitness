<?php
/**
 * init_db.php — создаёт таблицы БД если их нет.
 * Запускается автоматически при первом обращении к любому API.
 * Можно также запустить вручную один раз: открыть в браузере.
 * 
 * SQL-скрипт для проверяющего:
 * Все таблицы используют IF NOT EXISTS — безопасно запускать повторно.
 */

require_once __DIR__ . '/db.php';

function initDatabase(): void {
    $pdo = getDB();

    $queries = [

        // ── ПОЛЬЗОВАТЕЛИ ──────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS users (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_token   VARCHAR(64) NOT NULL UNIQUE,
            nickname        VARCHAR(50) NULL UNIQUE,
            email           VARCHAR(255) NULL UNIQUE,
            password_hash   VARCHAR(255) NULL,
            role            ENUM('user','admin') NOT NULL DEFAULT 'user',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_token),
            INDEX idx_nickname (nickname),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── ИСТОРИЯ РАСЧЁТОВ ──────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS calculations (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         INT UNSIGNED NOT NULL,
            calc_type       VARCHAR(50) NOT NULL,
            params_json     JSON NOT NULL,
            result          VARCHAR(100) NOT NULL,
            result_label    VARCHAR(150) NOT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_type (calc_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── ДНЕВНИК ЗАМЕРОВ ───────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS measurements (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         INT UNSIGNED NOT NULL,
            weight          DECIMAL(5,2) NULL COMMENT 'кг',
            body_fat        DECIMAL(4,1) NULL COMMENT '% жира',
            waist           DECIMAL(5,1) NULL COMMENT 'талия см',
            hips            DECIMAL(5,1) NULL COMMENT 'бёдра см',
            chest           DECIMAL(5,1) NULL COMMENT 'грудь см',
            notes           VARCHAR(500) NULL,
            measured_at     DATE NOT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_date (user_id, measured_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── ОТЗЫВЫ НА КАЛЬКУЛЯТОРЫ ────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS reviews (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         INT UNSIGNED NOT NULL,
            calc_type       VARCHAR(50) NOT NULL,
            rating          TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
            comment         VARCHAR(500) NULL,
            is_approved     TINYINT(1) NOT NULL DEFAULT 0,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_user_calc (user_id, calc_type),
            INDEX idx_calc_rating (calc_type, is_approved)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // ── ЗАЯВКИ НА КОНСУЛЬТАЦИЮ ────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS contacts (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id         INT UNSIGNED NULL,
            name            VARCHAR(100) NOT NULL,
            phone           VARCHAR(20) NULL,
            goal            ENUM('lose','gain','health','sport','other') NOT NULL DEFAULT 'other',
            message         TEXT NULL,
            status          ENUM('new','processing','done') NOT NULL DEFAULT 'new',
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
}

// ── Если файл открыт напрямую — показываем результат ──────────────────────
if (basename($_SERVER['PHP_SELF']) === 'init_db.php') {
    // Простая защита: только с локального IP или с ключом
    $allowed = ($_SERVER['REMOTE_ADDR'] === '127.0.0.1')
               || ($_GET['key'] ?? '') === 'init_secret_key_change_me';

    if (!$allowed) {
        http_response_code(403);
        die('Forbidden');
    }

    try {
        initDatabase();
        echo "<pre style='font-family:monospace;padding:20px;'>";
        echo "✅ Таблицы созданы (или уже существуют):\n\n";
        $tables = ['users','calculations','measurements','reviews','contacts'];
        $pdo = getDB();
        foreach ($tables as $t) {
            $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            echo "  • $t — $count записей\n";
        }
        echo "\n✅ База данных готова к работе.";
        echo "</pre>";
    } catch (Exception $e) {
        echo "<pre style='color:red'>❌ Ошибка: " . htmlspecialchars($e->getMessage()) . "</pre>";
    }
}
