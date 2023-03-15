<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Routing\Matcher\ExpressionLanguageProvider;
use Symfony\Component\Routing\RequestContext;

class ExpressionLanguageProviderTest extends TestCase
{
    private $context;
    private $expressionLanguage;

    protected function setUp(): void
    {
        $functionProvider = new ServiceLocator([
            'env' => fn () => fn (string $arg) => [
                'APP_ENV' => 'test',
                'PHP_VERSION' => '7.2',
            ][$arg] ?? null,
            'sum' => fn () => fn ($a, $b) => $a + $b,
            'foo' => fn () => fn () => 'bar',
        ]);

        $this->context = new RequestContext();
        $this->context->setParameter('_functions', $functionProvider);

        $this->expressionLanguage = new ExpressionLanguage();
        $this->expressionLanguage->registerProvider(new ExpressionLanguageProvider($functionProvider));
    }

    /**
     * @dataProvider compileProvider
     */
    public function testCompile(string $expression, string $expected)
    {
        $this->assertSame($expected, $this->expressionLanguage->compile($expression));
    }

    public static function compileProvider(): iterable
    {
        return [
            ['env("APP_ENV")', '($context->getParameter(\'_functions\')->get(\'env\')("APP_ENV"))'],
            ['sum(1, 2)', '($context->getParameter(\'_functions\')->get(\'sum\')(1, 2))'],
            ['foo()', '($context->getParameter(\'_functions\')->get(\'foo\')())'],
        ];
    }

    /**
     * @dataProvider evaluateProvider
     */
    public function testEvaluate(string $expression, $expected)
    {
        $this->assertSame($expected, $this->expressionLanguage->evaluate($expression, ['context' => $this->context]));
    }

    public static function evaluateProvider(): iterable
    {
        return [
            ['env("APP_ENV")', 'test'],
            ['env("PHP_VERSION")', '7.2'],
            ['env("unknown_env_variable")', null],
            ['sum(1, 2)', 3],
            ['foo()', 'bar'],
        ];
    }
}
