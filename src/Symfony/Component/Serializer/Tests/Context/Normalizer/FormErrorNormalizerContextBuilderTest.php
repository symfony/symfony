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
use Symfony\Component\Serializer\Context\Normalizer\FormErrorNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\FormErrorNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class FormErrorNormalizerContextBuilderTest extends TestCase
{
    private FormErrorNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new FormErrorNormalizerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withTitle($values[FormErrorNormalizer::TITLE])
            ->withType($values[FormErrorNormalizer::TYPE])
            ->withStatusCode($values[FormErrorNormalizer::CODE])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            FormErrorNormalizer::TITLE => 'title',
            FormErrorNormalizer::TYPE => 'type',
            FormErrorNormalizer::CODE => 418,
        ]];

        yield 'With null values' => [[
            FormErrorNormalizer::TITLE => null,
            FormErrorNormalizer::TYPE => null,
            FormErrorNormalizer::CODE => null,
        ]];
    }
}
