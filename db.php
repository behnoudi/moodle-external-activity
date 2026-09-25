<?php
// moodle/db.php

// غیرفعال کردن نمایش خطاهای مستقیم برای جلوگیری از خراب شدن خروجی‌های JSON
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

require_once __DIR__ . '/vendor/autoload.php';

// تنظیم فرجه زمانی برای اختلاف ساعت احتمالی سرورها
\Firebase\JWT\JWT::$leeway = 10;

use Packback\Lti1p3\LtiRegistration;
use Packback\Lti1p3\LtiDeployment;
use Packback\Lti1p3\Interfaces\IDatabase;
use Packback\Lti1p3\Interfaces\ICache;
use Packback\Lti1p3\Interfaces\ICookie;
use Packback\Lti1p3\Interfaces\ILtiRegistration;
use Packback\Lti1p3\Interfaces\ILtiDeployment;

define('PLATFORM_ID', 'https://lms3.alavischool.ir');
define('CLIENT_ID', 'y8J3mnXnphotHPw');
define('DEPLOYMENT_ID', '1');

define('AUTH_LOGIN_URL', PLATFORM_ID . '/mod/lti/auth.php');
define('AUTH_TOKEN_URL', PLATFORM_ID . '/mod/lti/token.php');
define('KEYSET_URL', PLATFORM_ID . '/mod/lti/certs.php');

$private_key = file_get_contents('/home/hsn/keys/private.key');

$registration = LtiRegistration::new()
    ->setIssuer(PLATFORM_ID)
    ->setClientId(CLIENT_ID)
    ->setKeySetUrl(KEYSET_URL)
    ->setAuthTokenUrl(AUTH_TOKEN_URL)
    ->setAuthLoginUrl(AUTH_LOGIN_URL)
    ->setToolPrivateKey($private_key);

// ۱. کلاس دیتابیس
class SimpleDatabase implements IDatabase {
    private $reg;
    public function __construct($reg) { $this->reg = $reg; }
    
    public function findRegistrationByIssuer(string $iss, ?string $clientId = null): ?ILtiRegistration { 
        return $this->reg; 
    }
    
    public function findDeployment(string $iss, string $deploymentId, ?string $clientId = null): ?ILtiDeployment {
        return LtiDeployment::new($deploymentId);
    }
}

// ۲. کلاس کش
class SessionCache implements ICache {
    public function getLaunchData(string $key): ?array { 
        return $_SESSION[$key] ?? null; 
    }
    public function cacheLaunchData(string $key, array $jwtBody): void { 
        $_SESSION[$key] = $jwtBody; 
    }
    public function cacheNonce(string $nonce, string $state): void { 
        $_SESSION['nonce_' . $nonce] = $state; 
    }
    public function checkNonceIsValid(string $nonce, string $state): bool { 
        return ($_SESSION['nonce_' . $nonce] ?? null) === $state; 
    }
    public function cacheAccessToken(string $key, string $accessToken): void { 
        $_SESSION['token_'.$key] = $accessToken; 
    }
    public function getAccessToken(string $key): ?string { 
        return $_SESSION['token_'.$key] ?? null; 
    }
    public function clearAccessToken(string $key): void { 
        unset($_SESSION['token_'.$key]); 
    }
}

// ۳. کلاس کوکی
class SessionCookie implements ICookie {
    public function getCookie(string $name): ?string { 
        return $_COOKIE[$name] ?? null; 
    }
    public function setCookie(string $name, string $value, $exp = 3600, $options = []): void {
        $options['expires'] = time() + $exp;
        $options['path'] = '/';
        $options['samesite'] = 'None';
        $options['secure'] = true;
        setcookie($name, $value, $options);
    }
}

$database = new SimpleDatabase($registration);
$cache = new SessionCache();
$cookie = new SessionCookie();