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

    /**
     * @dataProvider provideTestThrowsOnEmptyContextData
     */
    public function testThrowsOnEmptyContext(callable $factory)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one of the "context", "normalizationContext", or "denormalizationContext" options of annotation "Symfony\Component\Serializer\Annotation\Context" must be provided as a non-empty array.');

        $factory();
    }

    public function provideTestThrowsOnEmptyContextData(): iterable
    {
        yield 'constructor: empty args' => [function () { new Context([]); }];

        yield 'doctrine-style: value option as empty array' => [function () { new Context(['value' => []]); }];
        yield 'doctrine-style: context option as empty array' => [function () { new Context(['context' => []]); }];
        yield 'doctrine-style: context option not provided' => [function () { new Context(['groups' => ['group_1']]); }];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'named args: empty context' => [function () {
                eval('return new Symfony\Component\Serializer\Annotation\Context(context: []);');
            }];
        }
    }

    /**
     * @dataProvider provideTestThrowsOnNonArrayContextData
     */
    public function testThrowsOnNonArrayContext(array $options)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Option "%s" of annotation "%s" must be an array.', key($options), Context::class));

        new Context($options);
    }

    public function provideTestThrowsOnNonArrayContextData(): iterable
    {
        yield 'non-array context' => [['context' => 'not_an_array']];
        yield 'non-array normalization context' => [['normalizationContext' => 'not_an_array']];
        yield 'non-array denormalization context' => [['normalizationContext' => 'not_an_array']];
    }

    public function testInvalidGroupOption()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Parameter "groups" of annotation "%s" must be a string or an array of strings. Got "stdClass"', Context::class));

        new Context(['context' => ['foo' => 'bar'], 'groups' => ['fine', new \stdClass()]]);
    }

    public function testInvalidGroupArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Parameter "groups" of annotation "%s" must be a string or an array of strings. Got "stdClass"', Context::class));

        new Context([], ['foo' => 'bar'], [], [], ['fine', new \stdClass()]);
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
        $context = new Context([], ['foo' => 'bar']);

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
        self::assertDumpEquals($expectedDump, $factory());
    }

    public function provideValidInputs(): iterable
    {
        yield 'doctrine-style: with context option' => [
            function () { return new Context(['context' => ['foo' => 'bar']]); },
            $expected = <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -context: [
    "foo" => "bar",
  ]
  -normalizationContext: []
  -denormalizationContext: []
  -groups: []
}
DUMP
        ];

        yield 'constructor: with context arg' => [
            function () { return new Context([], ['foo' => 'bar']); },
            $expected,
        ];

        yield 'doctrine-style: with normalization context option' => [
            function () { return new Context(['normalizationContext' => ['foo' => 'bar']]); },
            $expected = <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -context: []
  -normalizationContext: [
    "foo" => "bar",
  ]
  -denormalizationContext: []
  -groups: []
}
DUMP
        ];

        yield 'constructor: with normalization context arg' => [
            function () { return new Context([], [], ['foo' => 'bar']); },
            $expected,
        ];

        yield 'doctrine-style: with denormalization context option' => [
            function () { return new Context(['denormalizationContext' => ['foo' => 'bar']]); },
            $expected = <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -context: []
  -normalizationContext: []
  -denormalizationContext: [
    "foo" => "bar",
  ]
  -groups: []
}
DUMP
        ];

        yield 'constructor: with denormalization context arg' => [
            function () { return new Context([], [], [], ['foo' => 'bar']); },
            $expected,
        ];

        yield 'doctrine-style: with groups option as string' => [
            function () { return new Context(['context' => ['foo' => 'bar'], 'groups' => 'a']); },
            <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -context: [
    "foo" => "bar",
  ]
  -normalizationContext: []
  -denormalizationContext: []
  -groups: [
    "a",
  ]
}
DUMP
        ];

        yield 'doctrine-style: with groups option as array' => [
            function () { return new Context(['context' => ['foo' => 'bar'], 'groups' => ['a', 'b']]); },
            $expected = <<<DUMP
Symfony\Component\Serializer\Annotation\Context {
  -context: [
    "foo" => "bar",
  ]
  -normalizationContext: []
  -denormalizationContext: []
  -groups: [
    "a",
    "b",
  ]
}
DUMP
        ];

        yield 'constructor: with groups arg' => [
            function () { return new Context([], ['foo' => 'bar'], [], [], ['a', 'b']); },
            $expected,
        ];
    }
}
