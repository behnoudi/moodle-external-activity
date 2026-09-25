<?php
require_once __DIR__ . '/db.php';

use Packback\Lti1p3\JwksEndpoint;

header('Content-Type: application/json; charset=utf-8');

try {
    if (method_exists(JwksEndpoint::class, 'fromRegistration')) {
        $jwks = JwksEndpoint::fromRegistration($registration)->getPublicJwks();
    } elseif (method_exists(JwksEndpoint::class, 'fromIssuer')) {
        $jwks = JwksEndpoint::fromIssuer($database, PLATFORM_ID)->getPublicJwks();
    } else {
        $jwks = JwksEndpoint::new(['default' => $private_key])->getPublicJwks();
    }

    echo json_encode($jwks, JSON_UNESCAPED_SLASHES);

} catch (Exception $e) {
    error_log("JWKS Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'خطا در بارگذاری کلید عمومی']);
}
