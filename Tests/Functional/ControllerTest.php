<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Functional;

use Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\App\Kernel;
use Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\App\Kernel44;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class ControllerTest extends WebTestCase
{
    public function testServerRequestAction()
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/server-request');

        self::assertResponseStatusCodeSame(200);
        self::assertSame('GET', $crawler->text());
    }

    public function testRequestAction()
    {
        $client = self::createClient();
        $crawler = $client->request('POST', '/request', [], [], [], 'some content');

        self::assertResponseStatusCodeSame(403);
        self::assertSame('POST some content', $crawler->text());
    }

    public function testMessageAction()
    {
        $client = self::createClient();
        $crawler = $client->request('PUT', '/message', [], [], ['HTTP_X_MY_HEADER' => 'some content']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('some content', $crawler->text());
    }

    protected static function getKernelClass(): string
    {
        return SymfonyKernel::VERSION_ID >= 50200 ? Kernel::class : Kernel44::class;
    }
}
