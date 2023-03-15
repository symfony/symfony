<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\Context;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class ContextTest extends TestCase
{
    use VarDumperTestTrait;

    protected function setUp(): void
    {
        $this->setUpVarDumper([], CliDumper::DUMP_LIGHT_ARRAY | CliDumper::DUMP_TRAILING_COMMA);
    }

    public function testThrowsOnEmptyContext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of the "context", "normalizationContext", or "denormalizationContext" options of annotation "Symfony\Component\Serializer\Annotation\Context" must be provided as a non-empty array.');

        new Context();
    }

    public function testInvalidGroupOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Parameter "groups" of annotation "%s" must be a string or an array of strings. Got "stdClass"', Context::class));

        new Context(context: ['foo' => 'bar'], groups: ['fine', new \stdClass()]);
    }

    public function testAsFirstArg()
    {
        $context = new Context(['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $context->getContext());
        self::assertEmpty($context->getNormalizationContext());
        self::assertEmpty($context->getDenormalizationContext());
        self::assertEmpty($context->getGroups());
    }

    public function testAsContextArg()
    {
        $context = new Context(context: ['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $context->getContext());
        self::assertEmpty($context->getNormalizationContext());
        self::assertEmpty($context->getDenormalizationContext());
        self::assertEmpty($context->getGroups());
    }

    /**
     * @dataProvider provideValidInputs
     */
    public function testValidInputs(callable $factory, string $expectedDump)
    {
        $this->assertDumpEquals($expectedDump, $factory());
    }

    public static function provideValidInputs(): iterable
    {
        yield 'named arguments: with context option' => [
            fn () => new Context(context: ['foo' => 'bar']),
            <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -groups: []
  -context: [
    "foo" => "bar",
  ]
  -normalizationContext: []
  -denormalizationContext: []
}
DUMP
        ];

        yield 'named arguments: with normalization context option' => [
            fn () => new Context(normalizationContext: ['foo' => 'bar']),
            <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -groups: []
  -context: []
  -normalizationContext: [
    "foo" => "bar",
  ]
  -denormalizationContext: []
}
DUMP
        ];

        yield 'named arguments: with denormalization context option' => [
            fn () => new Context(denormalizationContext: ['foo' => 'bar']),
            <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -groups: []
  -context: []
  -normalizationContext: []
  -denormalizationContext: [
    "foo" => "bar",
  ]
}
DUMP
        ];

        yield 'named arguments: with groups option as string' => [
            fn () => new Context(context: ['foo' => 'bar'], groups: 'a'),
            <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -groups: [
    "a",
  ]
  -context: [
    "foo" => "bar",
  ]
  -normalizationContext: []
  -denormalizationContext: []
}
DUMP
        ];

        yield 'named arguemnts: with groups option as array' => [
            fn () => new Context(context: ['foo' => 'bar'], groups: ['a', 'b']),
            <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -groups: [
    "a",
    "b",
  ]
  -context: [
    "foo" => "bar",
  ]
  -normalizationContext: []
  -denormalizationContext: []
}
DUMP
        ];
    }
}
