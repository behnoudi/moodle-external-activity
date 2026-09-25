<?php
// ============================================================================
// moodle/functions.php
// توابع کمکی سراسری که در chat-api.php ،launch.php و score.php استفاده می‌شوند.
// ============================================================================
require_once __DIR__ . '/config.php';

/**
 * بارگذاری امن یک تاپیک بر اساس کلید آن.
 * قبل از خواندن فایل، کلید با یک الگوی سخت‌گیرانه اعتبارسنجی می‌شود
 * تا از Path Traversal جلوگیری شود.
 */
function loadTopic(string $topicKey): ?array
{
    $topicKey = trim($topicKey);

    if ($topicKey === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $topicKey)) {
        return null;
    }

    $path = TOPICS_DIR . "/{$topicKey}.php";
    if (!is_file($path)) {
        return null;
    }

    $topic = require $path;
    if (!is_array($topic)) {
        return null;
    }

    $topic['key'] = $topicKey;
    return $topic;
}

/**
 * ساخت سیستم‌پرامپت کامل (ساندویچی) برای یک تاپیک مشخص.
 */
function getSystemPrompt(array $topic): string
{
    $fullPrompt = PROMPT_PRE_CURRICULUM . "\n\n" .
                  "[EXCLUSIVE_CURRICULUM]\n" .
                  trim($topic['curriculum_text']) . "\n" .
                  "[/EXCLUSIVE_CURRICULUM]\n\n" .
                  PROMPT_POST_CURRICULUM;

    return strtr($fullPrompt, [
        '{COURSE_TITLE}'         => $topic['course_title'],
        '{TARGET_AUDIENCE}'      => $topic['target_audience'],
        '{PROGRESSION_STRATEGY}' => trim($topic['progression_strategy']),
    ]);
}

/** پیام محرکِ مخفیِ آغاز گفتگو (هرگز برای دانش‌آموز نمایش داده نمی‌شود). */
function getInitialTriggerMessage(array $topic): string
{
    return $topic['opening_hook'];
}

/**
 * استخراج نشانه‌ی «مرحله‌ی جاری» (###STEP:n###) از ابتدای پیام هوش مصنوعی
 * و حذف آن از متن قابل‌نمایش.
 *
 * @return array{0: string, 1: ?int} [متنِ پاک‌شده، شماره‌ی مرحله یا null]
 */
function extractStepMarker(string $reply): array
{
    $prefix = preg_quote(STEP_MARK_PREFIX, '/');
    $suffix = preg_quote(STEP_MARK_SUFFIX, '/');
    $pattern = '/^\s*' . $prefix . '(\d+)' . $suffix . '\s*/su';

    if (preg_match($pattern, $reply, $m)) {
        $clean = preg_replace($pattern, '', $reply, 1);
        return [trim($clean), (int) $m[1]];
    }

    return [$reply, null];
}

/**
 * استخراج بلوک نمره‌ی پایانی از پاسخ هوش مصنوعی و حذف آن از متن قابل‌نمایش.
 *
 * @return array{0: string, 1: ?int, 2: ?string} [متنِ پاک‌شده، نمره یا null، بازخورد یا null]
 */
function extractGradeMarker(string $reply): array
{
    $start = preg_quote(GRADE_MARK_START, '/');
    $end   = preg_quote(GRADE_MARK_END, '/');
    $pattern = '/' . $start . '\s*SCORE:\s*(\d{1,3})\s*FEEDBACK:\s*(.*?)\s*' . $end . '/su';

    if (preg_match($pattern, $reply, $m)) {
        $clean    = trim(str_replace($m[0], '', $reply));
        $score    = max(0, min(SCORE_MAX, (int) $m[1]));
        $feedback = trim($m[2]);
        return [$clean, $score, $feedback];
    }

    return [$reply, null, null];
}

/**
 * لاگ اختصاصی و مستقل از تنظیمات سرور. همیشه در storage/debug.log ذخیره می‌شود
 * تا برای اشکال‌زدایی نیازی به پیدا کردن لاگ سیستم (nginx/php-fpm) نباشد.
 */
function debugLog(string $tag, string $message): void
{
    try {
        $dir = __DIR__ . '/storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . "] [{$tag}] {$message}" . PHP_EOL;
        @file_put_contents($dir . '/debug.log', $line, FILE_APPEND | LOCK_EX);
    } catch (\Throwable $e) {
        // اگر نوشتن لاگ هم شکست خورد، جریان اصلی برنامه نباید متوقف شود.
    }
}

/**
 * ارسال نمره‌ی نهایی و بازخورد به مودل از طریق سرویس AGS.
 * نیازمند وجود $_SESSION['lti_launch_id'] معتبر است.
 *
 * @return array{status: string, message: string}
 */
function submitGradeToMoodle($database, $cache, $cookie, float $score, string $feedbackText = ''): array
{
    if (!isset($_SESSION['lti_launch_id'])) {
        return ['status' => 'error', 'message' => 'نشست معتبر یافت نشد. لطفاً از مودل مجدداً وارد شوید.'];
    }

    try {
        $serviceConnector = new \Packback\Lti1p3\LtiServiceConnector($cache, new \GuzzleHttp\Client());
        $launch = \Packback\Lti1p3\LtiMessageLaunch::fromCache(
            $_SESSION['lti_launch_id'],
            $database,
            $cache,
            $cookie,
            $serviceConnector
        );

        if (!$launch->hasAgs()) {
            debugLog('GRADE', 'hasAgs() = false برای launch_id: ' . $_SESSION['lti_launch_id']);
            return ['status' => 'error', 'message' => 'سرویس ثبت نمره برای این فعالیت فعال نیست.'];
        }

        $ags = $launch->getAgs();

        $grade = \Packback\Lti1p3\LtiGrade::new()
            ->setScoreGiven($score)
            ->setScoreMaximum(SCORE_MAX)
            ->setTimestamp(date('c'))
            ->setActivityProgress('Completed')
            ->setGradingProgress('FullyGraded')
            ->setUserId($launch->getLaunchData()['sub']);

        // برخی نسخه‌های کتابخانه امکان ارسال کامنت/بازخورد را هم دارند.
        if ($feedbackText !== '' && method_exists($grade, 'setComment')) {
            $grade->setComment($feedbackText);
        }

        $ags->putGrade($grade);

        return ['status' => 'success', 'message' => 'نمره با موفقیت در دفتر نمرات مودل ثبت شد!'];

    } catch (\Exception $e) {
        error_log('LTI Grade Passback Error: ' . $e->getMessage());
        debugLog('GRADE', 'Exception: ' . $e->getMessage() . ' | ' . get_class($e));
        return ['status' => 'error', 'message' => 'خطا در ثبت نمره در مودل.'];
    }
}
