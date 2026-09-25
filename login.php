<?php
session_set_cookie_params(['samesite' => 'None', 'secure' => true]);
session_start();
require_once __DIR__ . '/db.php';

use Packback\Lti1p3\LtiOidcLogin;

try {
    $login = LtiOidcLogin::new($database, $cache, $cookie);
    $redirectUrl = $login->getRedirectUrl(LAUNCH_URL, $_REQUEST);

    header('Location: ' . $redirectUrl);
    exit;

} catch (Exception $e) {
    error_log("LTI Login Error: " . $e->getMessage());
    die("خطا در احراز هویت اولیه با مودل. لطفاً دوباره وارد سامانه شوید.");
}
