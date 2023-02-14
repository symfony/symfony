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
use Symfony\Component\Serializer\Context\Normalizer\AbstractObjectNormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class AbstractObjectNormalizerContextBuilderTest extends TestCase
{
    private AbstractObjectNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new class() extends AbstractObjectNormalizerContextBuilder {};
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withEnableMaxDepth($values[AbstractObjectNormalizer::ENABLE_MAX_DEPTH])
            ->withDepthKeyPattern($values[AbstractObjectNormalizer::DEPTH_KEY_PATTERN])
            ->withDisableTypeEnforcement($values[AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT])
            ->withSkipNullValues($values[AbstractObjectNormalizer::SKIP_NULL_VALUES])
            ->withSkipUninitializedValues($values[AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES])
            ->withMaxDepthHandler($values[AbstractObjectNormalizer::MAX_DEPTH_HANDLER])
            ->withExcludeFromCacheKeys($values[AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY])
            ->withDeepObjectToPopulate($values[AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE])
            ->withPreserveEmptyObjects($values[AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>|}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            AbstractObjectNormalizer::DEPTH_KEY_PATTERN => '%s_%s',
            AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => false,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => false,
            AbstractObjectNormalizer::MAX_DEPTH_HANDLER => static function (): void {},
            AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY => ['key'],
            AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => true,
            AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => false,
        ]];

        yield 'With null values' => [[
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => null,
            AbstractObjectNormalizer::DEPTH_KEY_PATTERN => null,
            AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => null,
            AbstractObjectNormalizer::SKIP_NULL_VALUES => null,
            AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES => null,
            AbstractObjectNormalizer::MAX_DEPTH_HANDLER => null,
            AbstractObjectNormalizer::EXCLUDE_FROM_CACHE_KEY => null,
            AbstractObjectNormalizer::DEEP_OBJECT_TO_POPULATE => null,
            AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => null,
        ]];
    }

    /**
     * @dataProvider validateDepthKeyPatternDataProvider
     */
    public function testValidateDepthKeyPattern(string $pattern, bool $expectException)
    {
        $exception = null;

        try {
            $this->contextBuilder->withDepthKeyPattern($pattern);
        } catch (InvalidArgumentException $e) {
            $exception = $e;
        }

        $this->assertSame($expectException, null !== $exception);
    }

    /**
     * @return iterable<array{0: string, 1: bool}>
     */
    public static function validateDepthKeyPatternDataProvider(): iterable
    {
        yield ['depth_%s::%s', false];
        yield ['%%%s %%s %%%%%s', false];
        yield ['%s%%%s', false];
        yield ['', true];
        yield ['depth_%d::%s', true];
        yield ['%s_%s::%s', true];
    }
}
