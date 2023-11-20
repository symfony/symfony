<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpClientController
{
    public function index(HttpClientInterface $httpClient, HttpClientInterface $symfonyHttpClient): Response
    {
        $httpClient->request('GET', 'https://symfony.com/');

        $symfonyHttpClient->request('GET', '/');
        $symfonyHttpClient->request('POST', '/', ['body' => 'foo']);
        $symfonyHttpClient->request('POST', '/', ['body' => ['foo' => 'bar']]);
        $symfonyHttpClient->request('POST', '/', ['json' => ['foo' => 'bar']]);
        $symfonyHttpClient->request('POST', '/', [
            'headers' => ['X-Test-Header' => 'foo'],
            'json' => ['foo' => 'bar'],
        ]);
        $symfonyHttpClient->request('GET', '/doc/current/index.html');

        return new Response();
    }
}
