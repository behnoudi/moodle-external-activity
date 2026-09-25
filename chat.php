<?php
// moodle/chat.php
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();

if (!isset($_SESSION['topic_data']) || !isset($_SESSION['lti_launch_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>نشست پیدا نشد</title>
        <style>
            body { font-family: Tahoma, sans-serif; background: #f8f9fa; display: flex; justify-content: center; align-items: center; height: 90vh; margin: 0; }
            .box { background: white; border-top: 5px solid #dc3545; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 500px; text-align: center; }
            h3 { color: #dc3545; margin-top: 0; }
            p { color: #555; line-height: 1.8; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="box">
            <h3>⛔ نشست شما پیدا نشد</h3>
            <p>لطفاً از طریق سامانه‌ی درس‌افزار (Moodle) دوباره وارد این فعالیت شوید.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$topic        = $_SESSION['topic_data'];
$studentName  = $_SESSION['student_name'] ?? 'دانش‌آموز عزیز';
$courseTitle  = $topic['course_title'] ?? 'کارگاه سقراطی';
$totalSteps   = $topic['total_steps'] ?? 5;
$stepLabels   = $topic['step_labels'] ?? [];
$currentStep  = $_SESSION['current_step'] ?? 1;
$gradeAlready = $_SESSION['grade_submitted'] ?? null;

// اطلاعات اولیه برای بوت‌استرپ جاوااسکریپت
$bootstrap = [
    'studentName'  => $studentName,
    'courseTitle'  => $courseTitle,
    'totalSteps'   => (int) $totalSteps,
    'stepLabels'   => (object) $stepLabels,
    'currentStep'  => (int) $currentStep,
    'grade'        => $gradeAlready,
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="chat.css">
</head>
<body>

    <div class="app-shell">

        <!-- ===================== هدر: عنوان + نوار پیشرفت ===================== -->
        <header class="app-header">
            <div class="header-top">
                <div class="mentor-badge" aria-hidden="true">🧭</div>
                <div class="header-text">
                    <h1 class="course-title"><?php echo htmlspecialchars($courseTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <span id="statusText" class="status-text">آماده‌ایم شروع کنیم، <?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?> 👋</span>
                </div>
                <button class="reset-btn" onclick="resetChat()" title="شروع دوباره‌ی کارگاه">
                    <svg viewBox="0 0 24 24" width="18" height="18"><path fill="currentColor" d="M17.65 6.35A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.74 10h-2.08A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h7V4z"/></svg>
                </button>
            </div>
            <div class="progress-track" id="progressTrack" role="list" aria-label="نقشه‌ی مراحل کارگاه"></div>
        </header>

        <!-- ===================== بدنه‌ی چت ===================== -->
        <main id="chatContainer" class="chat-body"></main>

        <!-- ===================== کارت پایانی نمره (مخفی تا زمان اعطا) ===================== -->
        <div id="gradeOverlay" class="grade-overlay hidden">
            <div class="grade-card">
                <div class="grade-trophy">🏆</div>
                <h2 class="grade-title">کارگاه را با موفقیت تمام کردی!</h2>
                <div class="grade-score" id="gradeScoreValue">۲۰ / ۲۰</div>
                <div class="grade-stars" id="gradeStars"></div>
                <p class="grade-feedback" id="gradeFeedbackText"></p>
                <p class="grade-status" id="gradeStatusText"></p>
                <button class="grade-close-btn" onclick="closeGradeOverlay()">بستن و مرور گفتگو</button>
            </div>
        </div>

        <!-- ===================== فوتر: ورودی پیام ===================== -->
        <footer class="chat-footer">
            <form id="chatForm" class="chat-input-row">
                <textarea
                    id="messageInput"
                    rows="1"
                    placeholder="پاسخت رو اینجا بنویس..."
                    required
                    autocomplete="off"></textarea>
                <button type="submit" id="sendBtn" class="send-btn" aria-label="ارسال">
                    <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M3 20l18-8L3 4v6l12 2-12 2z"/></svg>
                </button>
            </form>
        </footer>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
    <script>
        window.WORKSHOP_BOOTSTRAP = <?php echo json_encode($bootstrap, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="chat.js"></script>
</body>
</html>
