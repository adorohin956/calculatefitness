<?php
error_reporting(0);
ini_set('display_errors', '0');
/**
 * db.php — подключение к MySQL через PDO
 * Файл лежит в корне сайта. Подключение через require_once.
 * 
 * НАСТРОЙКИ ДЛЯ ХОСТИНГА:
 * Замени DB_HOST, DB_NAME, DB_USER, DB_PASS на свои данные из панели Beget.
 */

define('DB_HOST', 'cal7592093.mysql');
define('DB_NAME', 'cal7592093_account');
define('DB_USER', 'cal7592093_mysql');
define('DB_PASS', '4Jpjraw+'); // пароль БД
define('DB_CHARSET', 'utf8mb4');


function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // защита от SQL-инъекций
            ]);
        } catch (PDOException $e) {
            // Не показываем детали ошибки пользователю
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Ошибка подключения к базе данных']);
            exit;
        }
    }

    return $pdo;
}
