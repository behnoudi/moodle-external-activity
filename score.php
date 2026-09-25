<?php
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();
require_once __DIR__ . '/db.php';

use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiGrade;
use Packback\Lti1p3\LtiServiceConnector;
use GuzzleHttp\Client;

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$score = $input['score'] ?? 20;

if (!isset($_SESSION['lti_launch_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'نشست معتبر یافت نشد. لطفاً از مودل مجدداً وارد شوید.']);
    exit;
}

try {
    $serviceConnector = new LtiServiceConnector($cache, new Client());
    $launch = LtiMessageLaunch::fromCache($_SESSION['lti_launch_id'], $database, $cache, $cookie, $serviceConnector);

    if ($launch->hasAgs()) {
        $ags = $launch->getAgs();

        $grade = LtiGrade::new()
            ->setScoreGiven((float)$score)
            ->setScoreMaximum(20)
            ->setTimestamp(date('c'))
            ->setActivityProgress('Completed')
            ->setGradingProgress('FullyGraded')
            ->setUserId($launch->getLaunchData()['sub']);

        $ags->putGrade($grade);
        echo json_encode(['status' => 'success', 'message' => 'نمره با موفقیت در دفتر نمرات مودل ثبت شد!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'سرویس ثبت نمره برای این فعالیت فعال نیست.']);
    }
} catch (Exception $e) {
    error_log("LTI Grade Passback Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'خطا در ثبت نمره در مودل.']);
}