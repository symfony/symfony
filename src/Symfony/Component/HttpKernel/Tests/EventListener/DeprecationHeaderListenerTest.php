<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\DeprecationHeaderListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class DeprecationHeaderListenerTest extends TestCase
{
    public function testSubscribedEvents()
    {
        $this->assertSame(
            [KernelEvents::RESPONSE => 'onKernelResponse'],
            DeprecationHeaderListener::getSubscribedEvents(),
        );
    }

    public function testIgnoresRoutesWithoutDeprecation()
    {
        $response = $this->handle(null);

        $this->assertFalse($response->headers->has('Deprecation'));
    }

    public function testIgnoresANonArrayDeprecation()
    {
        $response = $this->handle('2026-01-01');

        $this->assertFalse($response->headers->has('Deprecation'));
    }

    public function testAddsTheDeprecationHeader()
    {
        $response = $this->handle(['since' => '2026-01-01']);

        $this->assertSame('@1767225600', $response->headers->get('Deprecation'));
        $this->assertFalse($response->headers->has('Sunset'));
        $this->assertFalse($response->headers->has('Link'));
    }

    public function testParsesDatesWithoutTimezoneAsUtc()
    {
        $response = $this->handle(['since' => '2026-01-01T01:00:00+01:00', 'sunset' => '2027-01-01 01:00:00']);

        $this->assertSame('@1767225600', $response->headers->get('Deprecation'));
        $this->assertSame('Fri, 01 Jan 2027 01:00:00 GMT', $response->headers->get('Sunset'));
    }

    public function testAppendsTheDeprecationLinkToTheExistingLinks()
    {
        $response = $this->handle(['since' => '2026-01-01', 'link' => 'https://example.com/deprecation'], new Response('', 200, ['Link' => '<https://example.com/next>; rel="next"']));

        $this->assertSame([
            '<https://example.com/next>; rel="next"',
            '<https://example.com/deprecation>; rel="deprecation"',
        ], $response->headers->all('link'));
    }

    public function testKeepsTheHeadersSetByTheController()
    {
        $response = $this->handle(['since' => '2026-01-01', 'sunset' => '2027-01-01', 'link' => 'https://example.com/deprecation'], new Response('', 200, ['Deprecation' => '@0']));

        $this->assertSame('@0', $response->headers->get('Deprecation'));
        $this->assertFalse($response->headers->has('Sunset'));
        $this->assertFalse($response->headers->has('Link'));
    }

    public function testKeepsTheSunsetHeaderSetByTheController()
    {
        $response = $this->handle(['since' => '2026-01-01', 'sunset' => '2027-01-01'], new Response('', 200, ['Sunset' => 'Sat, 01 Jan 2028 00:00:00 GMT']));

        $this->assertSame('@1767225600', $response->headers->get('Deprecation'));
        $this->assertSame('Sat, 01 Jan 2028 00:00:00 GMT', $response->headers->get('Sunset'));
    }

    public function testRejectsAMissingSinceDate()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "since" date of the deprecation of route "orders_v1" must be a string, "null" given.');

        $this->handle(['sunset' => '2027-01-01']);
    }

    public function testRejectsAnInvalidDate()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The "since" date of the deprecation of route "orders_v1" must be a valid date, "not a date" given.');

        $this->handle(['since' => 'not a date']);
    }

    #[DataProvider('provideInvalidLinks')]
    public function testSkipsAnInvalidLink(mixed $link)
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('was not sent'), ['route' => 'orders_v1', 'link' => $link]);

        $response = $this->handle(['since' => '2026-01-01', 'link' => $link], logger: $logger);

        $this->assertSame('@1767225600', $response->headers->get('Deprecation'));
        $this->assertFalse($response->headers->has('Link'));
    }

    public static function provideInvalidLinks(): iterable
    {
        yield 'not a string' => [['https://example.com']];
        yield 'empty' => [''];
        yield 'breaking the header' => ['https://example.com>; rel="next"'];
        yield 'line break' => ["https://example.com\r\nX-Foo: bar"];
    }

    public function testSkipsAnInvalidLinkWithoutLogger()
    {
        $response = $this->handle(['since' => '2026-01-01', 'link' => "https://example.com\r\nX-Foo: bar"]);

        $this->assertFalse($response->headers->has('Link'));
    }

    private function handle(mixed $deprecation, Response $response = new Response(), ?LoggerInterface $logger = null): Response
    {
        $request = new Request();
        $request->attributes->set('_route', 'orders_v1');
        $request->attributes->set('_deprecation', $deprecation);

        new DeprecationHeaderListener($logger)->onKernelResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response));

        return $response;
    }
}
