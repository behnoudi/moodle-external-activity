<?php
// moodle/chat-api.php
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// ===================== بررسی معتبر بودن نشست =====================
if (!isset($_SESSION['topic_data']) || !isset($_SESSION['lti_launch_id'])) {
    http_response_code(440);
    echo json_encode(['success' => false, 'error' => 'نشست شما منقضی شده. لطفاً دوباره از مودل وارد شوید.']);
    exit;
}

$topic = $_SESSION['topic_data'];

// تاریخچه‌ی گفتگو در سشن نگه‌داری می‌شود (به ازای هر launch یک تاریخچه‌ی تازه)
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [
        ['role' => 'system', 'content' => getSystemPrompt($topic)],
    ];
}
$history = &$_SESSION['chat_history'];

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? 'send';

// ===================== ریست کردن گفتگو =====================
if ($action === 'reset') {
    $_SESSION['chat_history'] = [
        ['role' => 'system', 'content' => getSystemPrompt($topic)],
    ];
    unset($_SESSION['grade_submitted']);
    unset($_SESSION['current_step']);
    echo json_encode(['success' => true, 'message' => 'گفتگو بازنشانی شد.']);
    exit;
}

// ===================== شروع گفتگو یا بازیابی تاریخچه =====================
if ($action === 'init') {
    if (count($history) > 1) {
        $historyForClient = array_values(array_filter($history, function ($msg) {
            return $msg['role'] !== 'system' && empty($msg['hidden']);
        }));
        echo json_encode([
            'success'        => true,
            'messages'       => $historyForClient,
            'topic_title'    => $topic['course_title'],
            'total_steps'    => $topic['total_steps'] ?? null,
            'step_labels'    => $topic['step_labels'] ?? null,
            'current_step'   => $_SESSION['current_step'] ?? 1,
            'grade_submitted'=> $_SESSION['grade_submitted'] ?? null,
        ]);
        exit;
    }

    // پیام محرکِ مخفی — هرگز در UI نمایش داده نمی‌شود
    $history[] = ['role' => 'user', 'content' => getInitialTriggerMessage($topic), 'hidden' => true];

} elseif ($action === 'send') {
    $userMessage = trim($input['message'] ?? '');
    if ($userMessage === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'متن پیام خالی است.']);
        exit;
    }
    $history[] = ['role' => 'user', 'content' => $userMessage];

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'عملیات نامعتبر است.']);
    exit;
}

// ===================== آماده‌سازی درخواست برای OpenRouter =====================
$apiMessages = array_map(function ($msg) {
    return ['role' => $msg['role'], 'content' => $msg['content']];
}, $history);

$payload = [
    'model'       => DEFAULT_MODEL,
    'messages'    => $apiMessages,
    'temperature' => 0.7,
    'max_tokens'  => 800,
];

$ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . OPENROUTER_API_KEY,
        'HTTP-Referer: ' . SITE_URL,
        'X-Title: ' . SITE_NAME,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT    => 60,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['success' => false, 'error' => 'خطای ارتباط با سرور: ' . $curlError]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 || isset($data['error'])) {
    $errMsg = $data['error']['message'] ?? 'خطایی در پردازش پاسخ رخ داد.';
    echo json_encode(['success' => false, 'error' => $errMsg]);
    exit;
}

$aiReplyRaw = $data['choices'][0]['message']['content'] ?? 'پاسخی دریافت نشد.';

// ===================== استخراج نشانه‌های نامرئی =====================
[$aiReply, $stepNumber]            = extractStepMarker($aiReplyRaw);
[$aiReply, $gradeScore, $feedback] = extractGradeMarker($aiReply);

if ($stepNumber !== null) {
    // ضامن امنیتی: مستقل از قضاوت مدل، هیچ‌وقت اجازه‌ی جهش بیش از یک مرحله
    // در هر نوبت یا برگشت به عقب را نمی‌دهیم. این باعث می‌شود حتی اگر مدل
    // در تشخیص «تسلط» اشتباه کند، نوار پیشرفت رفتار منطقی‌تری داشته باشد.
    $prevStep = $_SESSION['current_step'] ?? 1;
    if ($stepNumber > $prevStep + 1) {
        $stepNumber = $prevStep + 1;
    } elseif ($stepNumber < $prevStep) {
        $stepNumber = $prevStep;
    }
    $_SESSION['current_step'] = $stepNumber;
}

// ذخیره‌ی نسخه‌ی خام (با نشانه‌ها) در تاریخچه تا مدل زمینه را از دست ندهد،
// اما نسخه‌ی پاک‌شده به کلاینت برگردانده می‌شود.
$history[] = ['role' => 'assistant', 'content' => $aiReplyRaw];

$gradeResult = null;

// ===================== ثبت خودکار نمره در دفتر نمرات مودل =====================
if ($gradeScore !== null && empty($_SESSION['grade_submitted'])) {
    $submission = submitGradeToMoodle($database, $cache, $cookie, (float) $gradeScore, (string) $feedback);

    $gradeResult = [
        'score'    => $gradeScore,
        'max'      => SCORE_MAX,
        'feedback' => $feedback,
        'status'   => $submission['status'],
        'message'  => $submission['message'],
    ];

    // در هر دو حالت موفق/ناموفق دیگر تلاش دوباره برای ثبت نمی‌کنیم؛
    // در صورت خطا، فقط پیام خطا را همراه نمره به دانش‌آموز/معلم نشان می‌دهیم.
    $_SESSION['grade_submitted'] = $gradeResult;
}

echo json_encode([
    'success' => true,
    'reply'   => $aiReply,
    'step'    => $_SESSION['current_step'] ?? null,
    'grade'   => $gradeResult,
]);
