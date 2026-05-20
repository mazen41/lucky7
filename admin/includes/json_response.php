<?php
// Unified JSON response helpers
if (!function_exists('json_success')) {
    function json_success(array $data = [], string $message = 'OK', int $status = 200): void {
        if (headers_sent() === false) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
        }
        $payload = array_merge(['success' => true, 'message' => $message], $data);
        // Guarantee UTF-8 clean encoding
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        exit;
    }
}

if (!function_exists('json_error')) {
    function json_error(string $message = 'حدث خطأ غير متوقع', array $extra = [], int $status = 500): void {
        if (headers_sent() === false) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
        }
        $payload = array_merge(['success' => false, 'message' => $message], $extra);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        exit;
    }
}
