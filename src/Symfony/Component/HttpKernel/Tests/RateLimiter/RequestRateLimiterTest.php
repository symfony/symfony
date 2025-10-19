<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\RateLimiter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\RateLimiter\RequestRateLimiter;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class RequestRateLimiterTest extends TestCase
{
    public function testAddRateLimiterFactoriesWithInvalidService()
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnMap([
                ['limiter.test', new \stdClass()],
            ])
        ;
        $requestRateLimiter = new RequestRateLimiter($container, 'foo');

        $this->expectException(\InvalidArgumentException::class);
        $requestRateLimiter->addRateLimiterFactories(['limiter.test']);
    }

    public function testConsumeWithMultipleRateLimiterFactories()
    {
        $now = new \DateTimeImmutable();
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')
            ->willReturn(
                new RateLimit(0, $now, true, 1),
                new RateLimit(0, $now->add(\DateInterval::createFromDateString('1 hour')), true, 1),
                new RateLimit(0, $now->add(\DateInterval::createFromDateString('2 hour')), true, 1),
            );
        $rateLimiterFactory = $this->createStub(RateLimiterFactoryInterface::class);
        $rateLimiterFactory->method('create')->with('GHz8dC4d')->willReturn($limiter);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(3))
            ->method('get')
            ->willReturnMap([
                ['limiter.test_one', $rateLimiterFactory],
                ['limiter.test_two', $rateLimiterFactory],
                ['limiter.test_three', $rateLimiterFactory],
            ])
        ;

        $requestRateLimiter = new RequestRateLimiter($container, 'foo');

        $requestRateLimiter->addRateLimiterFactories(['limiter.test_one', 'limiter.test_two', 'limiter.test_three']);
        $requestRateLimiter->consume(new Request());
    }

    #[DataProvider('provideRequestsControllerFormats')]
    public function testConsume(Request $request, string $secret, string $expectedHash)
    {
        $limiter = $this->createStub(LimiterInterface::class);
        $rateLimiterFactory = $this->createStub(RateLimiterFactoryInterface::class);
        $rateLimiterFactory->method('create')->with($expectedHash)->willReturn($limiter);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')
            ->willReturnMap([
                ['limiter.test', $rateLimiterFactory],
            ])
        ;

        $requestRateLimiter = new RequestRateLimiter($container, $secret);

        $requestRateLimiter->addRateLimiterFactories(['limiter.test']);
        $requestRateLimiter->consume($request);
    }

    public static function provideRequestsControllerFormats(): \Traversable
    {
        yield 'String controller with method' => [
            new Request(
                attributes: ['_controller' => 'ControllerTest::index'],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'secret',
            'mGpCqMCv',
        ];

        yield 'String controller with different secret' => [
            new Request(
                attributes: ['_controller' => 'ControllerTest::index'],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'secret_two',
            'AGaZrRhz',
        ];

        yield 'String controller with different IP' => [
            new Request(
                attributes: ['_controller' => 'ControllerTest::index'],
                server: ['REMOTE_ADDR' => '172.16.254.1']
            ),
            'secret',
            'Op6bV.fS',
        ];

        yield 'String controller without method' => [
            new Request(
                attributes: ['_controller' => 'ControllerTest'],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'secret',
            'LEjfNOfb',
        ];

        yield 'Array controller' => [
            new Request(
                attributes: ['_controller' => ['ControllerTest', 'index']],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'secret',
            'mGpCqMCv',
        ];

        yield 'Object controller' => [
            new Request(
                attributes: ['_controller' => new \stdClass()],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'secret',
            'vLU7vlaI',
        ];

        yield 'Closure controller' => [
            new Request(
                attributes: ['_controller' => trim(...)],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'secret',
            'cfjnamcd',
        ];

        yield 'Null controller' => [
            new Request(
                attributes: ['_controller' => null],
                server: ['REMOTE_ADDR' => '127.0.0.1']
            ),
            'secret',
            'p5SbHEUZ',
        ];
    }
}
