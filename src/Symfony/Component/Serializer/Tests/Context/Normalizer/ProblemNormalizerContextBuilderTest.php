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
use Symfony\Component\Serializer\Context\Normalizer\ProblemNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class ProblemNormalizerContextBuilderTest extends TestCase
{
    private ProblemNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new ProblemNormalizerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withTitle($values[ProblemNormalizer::TITLE])
            ->withType($values[ProblemNormalizer::TYPE])
            ->withStatusCode($values[ProblemNormalizer::STATUS])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            ProblemNormalizer::TITLE => 'title',
            ProblemNormalizer::TYPE => 'type',
            ProblemNormalizer::STATUS => 418,
        ]];

        yield 'With null values' => [[
            ProblemNormalizer::TITLE => null,
            ProblemNormalizer::TYPE => null,
            ProblemNormalizer::STATUS => null,
        ]];
    }
}
