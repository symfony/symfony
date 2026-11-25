<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\ContainerQuery;
use Symfony\Component\HtmlRenderer\Css\CssParser;
use Symfony\Component\HtmlRenderer\Css\CustomProperties;
use Symfony\Component\HtmlRenderer\Css\FunctionEvaluator;
use Symfony\Component\HtmlRenderer\Css\PropertySyntax;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

final class FunctionEvaluatorTest extends TestCase
{
    public function testNestedCallsTypedDefaultsLocalScopeAndConditions()
    {
        $evaluator = $this->evaluator('@function --identity(--value <color>) returns <color>{result:var(--value)}@function --choose(--value <color>:red) returns <color>{--local:--identity(var(--value));result:blue;@media (wide){result:if(style(--enabled:true):var(--local);else:green)}}');
        self::assertSame('red', $evaluator->resolve('--choose()', ['--enabled' => 'true']));
        self::assertSame('green', $evaluator->resolve('--choose()', []));
        self::assertSame('purple', $evaluator->resolve('--choose(purple)', ['--enabled' => 'true']));
        self::assertSame('"--choose(red)" --unknown(blue)', $evaluator->resolve('"--choose(red)" --unknown(blue)', []));
    }

    public function testMissingParameterAllowsVarFallbackAndInvalidDescriptorIsIgnored()
    {
        $evaluator = $this->evaluator('@function --fallback(--value){result:var(--value,red);result:var(--missing)}');
        self::assertSame('red', $evaluator->resolve('--fallback()', ['--value' => 'blue']));
    }

    public function testInvalidTypedArgumentIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator('@function --color(--value <color>) returns <color>{result:var(--value)}')->resolve('--color(12px)', []);
    }

    public function testCyclesAreBounded()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->evaluator('@function --cycle(){result:--cycle()}')->resolve('--cycle()', []);
    }

    public function testExpandedValueSizeIsBoundedBeforeAllocation()
    {
        $evaluator = $this->evaluator('@function --blob(){result:'.str_repeat('x', 33000).'}');
        self::assertSame(33000, \strlen($evaluator->resolve('--blob()', [])));
        $this->expectException(InvalidArgumentException::class);
        $evaluator->resolve('--blob() --blob()', []);
    }

    private function evaluator(string $css): FunctionEvaluator
    {
        $parser = new CssParser();
        $parser->parse($css);

        return new FunctionEvaluator($parser->takeFunctions(), CustomProperties::substitute(...), PropertySyntax::matches(...), static fn (array $scope): \Closure => static fn (string $kind, string $argument): bool => 'media' === $kind ? '(wide)' === $argument : ('style' === $kind && ContainerQuery::parse('style('.$argument.')')->matchesStyle($scope)));
    }
}
