<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Test\Fixtures;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebTestCaseController
{
    public function ok(): Response
    {
        $response = new Response('', 200, [
            'Cache-Control' => 'no-cache, private',
        ]);
        $response->setDate(new \DateTimeImmutable());
        $response->headers->setCookie(new Cookie('foo', 'bar'));

        return $response;
    }

    public function customFormat(): Response
    {
        return new Response('', 200, [
            'Content-Type' => 'application/vnd.myformat',
        ]);
    }

    public function jsonldFormat(): Response
    {
        return new Response('', 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    public function noFormat(): Response
    {
        return new Response('', 200, [
            'Content-Type' => '',
        ]);
    }

    public function notFound(): Response
    {
        return new Response('', 404);
    }

    public function movedPermanently(): Response
    {
        return new RedirectResponse('https://example.com/', 301);
    }

    public function found(): Response
    {
        return new RedirectResponse('https://example.com/', 302);
    }

    public function internalServerError(): Response
    {
        return new Response('', 500, [
            'X-Debug-Exception' => 'An exception has occurred',
            'X-Debug-Exception-File' => '%2Fsrv%2Ftest.php:12',
        ]);
    }

    public function crawler(string $content): Response
    {
        return new Response(urldecode($content));
    }

    public function requestAttribute(Request $request): Response
    {
        $request->attributes->set('foo', 'bar');

        return new Response();
    }

    public function homepage(): Response
    {
        return new Response();
    }
}
