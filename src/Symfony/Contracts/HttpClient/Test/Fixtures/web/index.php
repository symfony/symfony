<?php

if ('cli-server' !== \PHP_SAPI) {
    // safe guard against unwanted execution
    throw new \Exception("You cannot run this script directly, it's a fixture for TestHttpServer.");
}

$vars = [];

if (!$_POST) {
    $_POST = json_decode(file_get_contents('php://input'), true);
    $_POST['content-type'] = $_SERVER['HTTP_CONTENT_TYPE'] ?? '?';
}

$headers = [
    'SERVER_PROTOCOL',
    'SERVER_NAME',
    'REQUEST_URI',
    'REQUEST_METHOD',
    'PHP_AUTH_USER',
    'PHP_AUTH_PW',
    'REMOTE_ADDR',
    'REMOTE_PORT',
];

foreach ($headers as $k) {
    if (isset($_SERVER[$k])) {
        $vars[$k] = $_SERVER[$k];
    }
}

foreach ($_SERVER as $k => $v) {
    if (str_starts_with($k, 'HTTP_')) {
        $vars[$k] = $v;
    }
}

$json = json_encode($vars, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

switch (parse_url($vars['REQUEST_URI'], \PHP_URL_PATH)) {
    default:
        exit;

    case '/head':
        header('X-Request-Vars: '.json_encode($vars, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
        header('Content-Length: '.strlen($json), true);
        break;

    case '/':
    case '/?a=a&b=b':
    case 'http://127.0.0.1:8057/':
    case 'http://localhost:8057/':
        ob_start('ob_gzhandler');
        break;

    case '/103':
        header('HTTP/1.1 103 Early Hints');
        header('Link: </style.css>; rel=preload; as=style', false);
        header('Link: </script.js>; rel=preload; as=script', false);
        flush();
        usleep(1000);
        echo "HTTP/1.1 200 OK\r\n";
        echo "Date: Fri, 26 May 2017 10:02:11 GMT\r\n";
        echo "Content-Length: 13\r\n";
        echo "\r\n";
        echo 'Here the body';
        exit;

    case '/404':
        header('Content-Type: application/json', true, 404);
        break;

    case '/404-gzipped':
        header('Content-Type: text/plain', true, 404);
        ob_start('ob_gzhandler');
        @ob_flush();
        flush();
        usleep(300000);
        echo 'some text';
        exit;

    case '/301':
        if ('Basic Zm9vOmJhcg==' === $vars['HTTP_AUTHORIZATION']) {
            header('Location: http://127.0.0.1:8057/302', true, 301);
        }
        break;

    case '/301/bad-tld':
        header('Location: http://foo.example.', true, 301);
        break;

    case '/301/invalid':
        header('Location: //?foo=bar', true, 301);
        break;

    case '/301/proxy':
    case 'http://localhost:8057/301/proxy':
    case 'http://127.0.0.1:8057/301/proxy':
        header('Location: http://localhost:8057/', true, 301);
        break;

    case '/302':
        if (!isset($vars['HTTP_AUTHORIZATION'])) {
            $location = $_GET['location'] ?? 'http://localhost:8057/';
            header('Location: '.$location, true, 302);
        }
        break;

    case '/302/relative':
        header('Location: ..', true, 302);
        break;

    case '/304':
        header('Content-Length: 10', true, 304);
        echo '12345';

        return;

    case '/307':
        header('Location: http://localhost:8057/post', true, 307);
        break;

    case '/length-broken':
        header('Content-Length: 1000');
        break;

    case '/post':
        $output = json_encode($_POST + ['REQUEST_METHOD' => $vars['REQUEST_METHOD']], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        header('Content-Type: application/json', true);
        header('Content-Length: '.strlen($output));
        echo $output;
        exit;

    case '/timeout-header':
        usleep(300000);
        break;

    case '/timeout-body':
        echo '<1>';
        @ob_flush();
        flush();
        usleep(500000);
        echo '<2>';
        exit;

    case '/timeout-long':
        ignore_user_abort(false);
        sleep(1);
        while (true) {
            echo '<1>';
            @ob_flush();
            flush();
            usleep(500);
        }
        exit;

    case '/chunked':
        header('Transfer-Encoding: chunked');
        echo "8\r\nSymfony \r\n5\r\nis aw\r\n6\r\nesome!\r\n0\r\n\r\n";
        exit;

    case '/chunked-broken':
        header('Transfer-Encoding: chunked');
        echo "8\r\nSymfony \r\n5\r\nis aw\r\n6\r\ne";
        exit;

    case '/gzip-broken':
        header('Content-Encoding: gzip');
        echo str_repeat('-', 1000);
        exit;

    case '/max-duration':
        ignore_user_abort(false);
        while (true) {
            echo '<1>';
            @ob_flush();
            flush();
            usleep(500);
        }
        exit;

    case '/json':
        header('Content-Type: application/json');
        echo json_encode([
            'documents' => [
                ['id' => '/json/1'],
                ['id' => '/json/2'],
                ['id' => '/json/3'],
            ],
        ]);
        exit;

    case '/json/1':
    case '/json/2':
    case '/json/3':
        header('Content-Type: application/json');
        echo json_encode([
            'title' => $vars['REQUEST_URI'],
        ]);

        exit;

    case '/custom':
        if (isset($_GET['status'])) {
            http_response_code((int) $_GET['status']);
        }
        if (isset($_GET['headers']) && is_array($_GET['headers'])) {
            foreach ($_GET['headers'] as $header) {
                header($header);
            }
        }
}

header('Content-Type: application/json', true);

echo $json;
