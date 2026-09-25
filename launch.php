<?php
// moodle/launch.php
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();
require_once __DIR__ . '/db.php';

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
$user_id      = $launch_data['sub'];

// ۱. دریافت پارامتر موضوع از مودل
$custom_params = $launch_data['https://purl.imsglobal.org/spec/lti/claim/custom'] ?? [];
$topic_key     = trim($custom_params['topic'] ?? '');

// ۲. اعتبارسنجی امنیتی: آیا تاپیک وارد شده و معتبر است؟
// جلوگیری از کاراکترهای غیرمجاز برای امنیت مسیر فایل (Path Traversal Protection)
$is_valid_format = preg_match('/^[a-zA-Z0-9_-]+$/', $topic_key);
$topic_file      = __DIR__ . "/topics/{$topic_key}.php";

if (empty($topic_key) || !$is_valid_format || !file_exists($topic_file)) {
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

// ۳. اگر تاپیک معتبر بود، اطلاعات سناریو را بخوان
$topic_data = require $topic_file;

// ذخیره اطلاعات جلسه برای چت و ثبت نمره
$_SESSION['lti_launch_id'] = $launch->getLaunchId();
$_SESSION['student_name']  = $student_name;
$_SESSION['topic_key']     = $topic_key;
$_SESSION['topic_data']    = $topic_data;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($topic_data['title'] ?? 'دستیار سقراطی'); ?></title>
    <style>
        body { font-family: Tahoma, sans-serif; padding: 25px; background: #f4f6f8; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); max-width: 650px; margin: auto; }
        .title { color: #0056b3; margin-top: 0; }
    </style>
</head>
<body>
    <div class="card">
        <h2 class="title"><?php echo htmlspecialchars($topic_data['title']); ?></h2>
        <p>سلام <strong><?php echo htmlspecialchars($student_name); ?></strong> عزیز! 👋</p>
        <p><?php echo htmlspecialchars($topic_data['description'] ?? ''); ?></p>
        
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        
        <div style="background: #e9f5ff; padding: 15px; border-radius: 6px; border-right: 4px solid #007bff; margin-bottom: 20px;">
            <strong>دستیار هوشمند:</strong><br>
            <?php echo htmlspecialchars($topic_data['starter_message']); ?>
        </div>
    </div>
</body>
</html>