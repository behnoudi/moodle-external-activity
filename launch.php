<?php
// moodle/launch.php
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

use Packback\Lti1p3\LtiMessageLaunch;
use Packback\Lti1p3\LtiServiceConnector;
use GuzzleHttp\Client;

try {
    $serviceConnector = new LtiServiceConnector($cache, new Client());
    $launch = LtiMessageLaunch::new($database, $cache, $cookie, $serviceConnector);

    $requestData = !empty($_POST) ? $_POST : $_REQUEST;
    $launch->initialize($requestData);

} catch (Exception $e) {
    error_log("LTI Launch Error: " . $e->getMessage());
    die("خطا در ورود به برنامه. لطفاً از طریق سامانه درس افزار مجدداً وارد شوید.");
}

$launch_data  = $launch->getLaunchData();
$student_name = $launch_data['name'] ?? 'دانش‌آموز گرامی';

// ۱. دریافت پارامتر موضوع از مودل
$custom_params = $launch_data['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
$topic_key     = trim($custom_params['topic'] ?? '');

// ۲. بارگذاری امن تاپیک (اعتبارسنجی و جلوگیری از Path Traversal داخل loadTopic انجام می‌شود)
$topic = loadTopic($topic_key);

if ($topic === null) {
    // اگر تاپیک وارد نشده بود یا غلط بود، فوراً متوقف شو و این صفحه ساده را نشان بده:
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>عدم دسترسی به فعالیت</title>
        <style>
            body { font-family: Tahoma, sans-serif; background: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 90vh; margin: 0; }
            .box { background: white; border-top: 5px solid #dc3545; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
            h3 { color: #dc3545; margin-top: 0; }
            p { color: #555; line-height: 1.8; font-size: 14px; }
            .hint { font-size: 12px; color: #888; margin-top: 20px; border-top: 1px dashed #ddd; padding-top: 10px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h3>⚠️ تنظیمات این جلسه هنوز تکمیل نشده است.</h3>
            <p>در صورت بروز مشکل آن را با آقای بهنودی در میان بگذارید.</p>
        </div>
    </body>
    </html>
    <?php
    exit; // توقف کامل و عدم مصرف توکن
}

// ۳. ذخیره اطلاعات جلسه برای چت و ثبت نمره
$_SESSION['lti_launch_id'] = $launch->getLaunchId();
$_SESSION['student_name']  = $student_name;
$_SESSION['topic_key']     = $topic_key;
$_SESSION['topic_data']    = $topic;

// شروع یک نشست چتِ تازه برای این launch (تاریخچه و وضعیت نمره‌ی قبلی پاک می‌شود)
unset($_SESSION['chat_history']);
unset($_SESSION['grade_submitted']);
unset($_SESSION['current_step']);

// ۴. هدایت به رابط چت
header('Location: chat.php');
exit;
