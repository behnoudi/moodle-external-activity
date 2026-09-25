<?php
// moodle/score.php
// این فایل برای ثبتِ دستیِ نمره (یا فراخوانی مستقل) نگه داشته شده است.
// در جریان عادی کارگاه، chat-api.php به‌طور خودکار همین منطق را
// از طریق تابع submitGradeToMoodle() در functions.php صدا می‌زند.
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$input    = json_decode(file_get_contents('php://input'), true);
$score    = isset($input['score']) ? (float) $input['score'] : (float) SCORE_MAX;
$feedback = trim($input['feedback'] ?? '');

$result = submitGradeToMoodle($database, $cache, $cookie, $score, $feedback);

echo json_encode($result);
