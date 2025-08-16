<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\Twig\Extension\ExpressionExtension;
use Symfony\Bridge\Twig\Extension\RateLimiterExtension;
use Symfony\Bridge\Twig\Extension\RateLimiterRuntime;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

#[RequiresMethod(RateLimiterFactory::class, '__construct')]
class RateLimiterExtensionTest extends TestCase
{
    #[DataProvider('provideRateLimitTemplatesUsingExpression')]
    public function testRateLimiterWithExpression(
        string $templateFile,
        bool $isAccepted,
        int|string $expectedConsume,
        Expression $expression,
        string $expectedOutput,
    ): void {
        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $expectedKey = (new ExpressionLanguage())->evaluate($expression, ['request' => $request]);

        $this->doTestRateLimiter(
            templateFile: $templateFile,
            isAccepted: $isAccepted,
            expectedConsume: $expectedConsume,
            expectedLimiterKey: $expectedKey,
            expectedOutput: $expectedOutput,
            requestStack: $requestStack
        );
    }

    public static function provideRateLimitTemplatesUsingExpression(): array
    {
        $expression = new Expression('request.getClientIp()');

        return [
            ['expression_key.twig', true, 1, $expression, 'expression_key: allowed'],
            ['expression_key.twig', false, 1, $expression, 'expression_key: denied'],
        ];
    }

    #[DataProvider('provideRateLimitTemplatesWithoutExpression')]
    public function testRateLimiterWithoutExpression(
        string $templateFile,
        bool $isAccepted,
        int|string $expectedConsume,
        string $expectedKey,
        string $expectedOutput,
    ): void {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $this->doTestRateLimiter(
            templateFile: $templateFile,
            isAccepted: $isAccepted,
            expectedConsume: $expectedConsume,
            expectedLimiterKey: $expectedKey,
            expectedOutput: $expectedOutput,
            requestStack: $requestStack
        );
    }

    public static function provideRateLimitTemplatesWithoutExpression(): array
    {
        return [
            ['custom_key.twig', true, 50, 'custom_key', 'custom_key: allowed'],
            ['custom_key.twig', false, 50, 'custom_key', 'custom_key: denied'],
            ['exceeded_limit.twig', false, 1, 'exceed_key', 'exceeded_limit: denied'],
            ['exceeded_limit.twig', true, 1, 'exceed_key', 'exceeded_limit: allowed'],
        ];
    }

    public function testRateLimiterThrowsIfServiceIsNotFactory()
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->with('limiter.invalid_service')
            ->willReturn(new \stdClass());

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $runtime = new RateLimiterRuntime($container, $requestStack);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The service "invalid_service" is not an instance of');

        $runtime->rateLimit('invalid_service');
    }

    private function doTestRateLimiter(
        string $templateFile,
        bool $isAccepted,
        int|string $expectedConsume,
        int|string $expectedLimiterKey,
        string $expectedOutput,
        RequestStack $requestStack,
    ): void {
        $rateLimit = $this->createMock(RateLimit::class);
        $rateLimit->expects($this->once())
            ->method('isAccepted')
            ->willReturn($isAccepted);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects($this->once())
            ->method('consume')
            ->with($expectedConsume)
            ->willReturn($rateLimit);

        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($expectedLimiterKey)
            ->willReturn($limiter);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('limiter.anonymous_api')
            ->willReturn($factory);

        $runtime = new RateLimiterRuntime($container, $requestStack);
        $twig = $this->createTwigEnvironment($runtime);

        $templateCode = file_get_contents(__DIR__.'/Fixtures/templates/rate_limiter/'.$templateFile);
        $twig->setLoader(new ArrayLoader(['template' => $templateCode]));

        $this->assertSame($expectedOutput, trim($twig->render('template')));
    }

    private function createTwigEnvironment(RateLimiterRuntime $rateLimiterRuntime): Environment
    {
        $twig = new Environment(new ArrayLoader([]), ['debug' => true, 'cache' => false]);
        $twig->addExtension(new RateLimiterExtension());
        $twig->addExtension(new ExpressionExtension());

        $loader = $this->createMock(RuntimeLoaderInterface::class);
        $loader->method('load')
            ->willReturnMap([
                [RateLimiterRuntime::class, $rateLimiterRuntime],
            ]);

        $twig->addRuntimeLoader($loader);

        return $twig;
    }
}
