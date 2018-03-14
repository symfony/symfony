<?php

namespace Symfony\Component\HttpFoundation\Tests;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../../../../../../vendor/autoload.php';

$uri = $_SERVER['REQUEST_URI'];

switch ($uri) {
    case '/ping':
        $response = new Response('pong');
        break;

    case '/cookie/samesite-lax':
        $response = new Response($uri);
        $response->headers->setCookie(new Cookie('SF', 'V', 0, '/cookie', 'example.org', true, true, false, Cookie::SAMESITE_LAX));
        break;

    case '/cookie/samesite-strict':
        $response = new Response($uri);
        $response->headers->setCookie(new Cookie('SF', 'V', 0, null, null, true, true, false, Cookie::SAMESITE_STRICT));
        break;

    default:
        $response = new Response('', Response::HTTP_NOT_IMPLEMENTED);
        break;
}

$response->send();
