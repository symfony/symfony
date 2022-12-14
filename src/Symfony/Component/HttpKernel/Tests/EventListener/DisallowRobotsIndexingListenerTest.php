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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\DisallowRobotsIndexingListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class DisallowRobotsIndexingListenerTest extends TestCase
{
    /**
     * @dataProvider provideResponses
     */
    public function testInvoke(?string $expected, array $responseArgs)
    {
        $response = new Response(...$responseArgs);
        $listener = new DisallowRobotsIndexingListener();

        $event = new ResponseEvent($this->createMock(HttpKernelInterface::class), new Request(), KernelInterface::MAIN_REQUEST, $response);

        $listener->onResponse($event);

        $this->assertSame($expected, $response->headers->get('X-Robots-Tag'), 'Header doesn\'t match expectations');
    }

    public static function provideResponses(): iterable
    {
        yield 'No header' => ['noindex', []];

        yield 'Header already set' => [
            'something else',
            ['', 204, ['X-Robots-Tag' => 'something else']],
        ];
    }
}
