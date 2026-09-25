<?php

declare(strict_types=1);

// Router for the built-in PHP server used by CurlTransportTest

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH);

header('Content-Type: application/json');

switch ($path) {
    case '/echo':
        $files = [];
        foreach ($_FILES as $name => $file) {
            if (is_array($file) && is_string($file['name'] ?? null) && is_string($file['tmp_name'] ?? null)) {
                $files[$name] = ['name' => $file['name'], 'contents' => file_get_contents($file['tmp_name'])];
            }
        }

        echo json_encode([
            'method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'post' => $_POST,
            'files' => $files,
        ]);
        break;

    case '/redirect':
        header('Location: /echo', true, 302);
        break;

    case '/file':
        header('Content-Type: application/octet-stream');
        echo str_repeat('0123456789', 100000);
        break;

    case '/slow':
        sleep(3);
        echo '{}';
        break;

    default:
        http_response_code(404);
        echo '{"ok":false}';
}
