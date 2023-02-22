<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\Normalizer\AbstractNormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class AbstractNormalizerContextBuilderTest extends TestCase
{
    private AbstractNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new class() extends AbstractNormalizerContextBuilder {};
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withCircularReferenceLimit($values[AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT])
            ->withObjectToPopulate($values[AbstractNormalizer::OBJECT_TO_POPULATE])
            ->withGroups($values[AbstractNormalizer::GROUPS])
            ->withAttributes($values[AbstractNormalizer::ATTRIBUTES])
            ->withAllowExtraAttributes($values[AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES])
            ->withDefaultContructorArguments($values[AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS])
            ->withCallbacks($values[AbstractNormalizer::CALLBACKS])
            ->withCircularReferenceHandler($values[AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER])
            ->withIgnoredAttributes($values[AbstractNormalizer::IGNORED_ATTRIBUTES])
            ->toArray();

        $this->assertEquals($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>|}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT => 12,
            AbstractNormalizer::OBJECT_TO_POPULATE => new \stdClass(),
            AbstractNormalizer::GROUPS => ['group'],
            AbstractNormalizer::ATTRIBUTES => ['attribute1', 'attribute2'],
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => true,
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => [self::class => ['foo' => 'bar']],
            AbstractNormalizer::CALLBACKS => [static function (): void {}],
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => static function (): void {},
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['attribute3'],
        ]];

        yield 'With null values' => [[
            AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT => null,
            AbstractNormalizer::OBJECT_TO_POPULATE => null,
            AbstractNormalizer::GROUPS => null,
            AbstractNormalizer::ATTRIBUTES => null,
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => null,
            AbstractNormalizer::DEFAULT_CONSTRUCTOR_ARGUMENTS => null,
            AbstractNormalizer::CALLBACKS => null,
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => null,
            AbstractNormalizer::IGNORED_ATTRIBUTES => null,
        ]];
    }

    public function testCastSingleGroupToArray()
    {
        $this->assertSame([AbstractNormalizer::GROUPS => ['group']], $this->contextBuilder->withGroups('group')->toArray());
    }

    public function testCannotSetNonStringAttributes()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->contextBuilder->withAttributes(['attribute', ['nested attribute', 1]]);
    }
}
