<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\SerializerContextBuilder;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class SerializerContextBuilderTest extends TestCase
{
    private SerializerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new SerializerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withEmptyArrayAsObject($values[Serializer::EMPTY_ARRAY_AS_OBJECT])
            ->withCollectDenormalizationErrors($values[DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            Serializer::EMPTY_ARRAY_AS_OBJECT => true,
            DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => false,
        ]];

        yield 'With null values' => [[
            Serializer::EMPTY_ARRAY_AS_OBJECT => null,
            DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => null,
        ]];
    }
}
