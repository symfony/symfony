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
use Symfony\Component\Serializer\Context\Normalizer\ConstraintViolationListNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class ConstraintViolationListNormalizerContextBuilderTest extends TestCase
{
    private ConstraintViolationListNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new ConstraintViolationListNormalizerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withInstance($values[ConstraintViolationListNormalizer::INSTANCE])
            ->withStatus($values[ConstraintViolationListNormalizer::STATUS])
            ->withTitle($values[ConstraintViolationListNormalizer::TITLE])
            ->withType($values[ConstraintViolationListNormalizer::TYPE])
            ->withPayloadFields($values[ConstraintViolationListNormalizer::PAYLOAD_FIELDS])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            ConstraintViolationListNormalizer::INSTANCE => new \stdClass(),
            ConstraintViolationListNormalizer::STATUS => 418,
            ConstraintViolationListNormalizer::TITLE => 'title',
            ConstraintViolationListNormalizer::TYPE => 'type',
            ConstraintViolationListNormalizer::PAYLOAD_FIELDS => ['field'],
        ]];

        yield 'With null values' => [[
            ConstraintViolationListNormalizer::INSTANCE => null,
            ConstraintViolationListNormalizer::STATUS => null,
            ConstraintViolationListNormalizer::TITLE => null,
            ConstraintViolationListNormalizer::TYPE => null,
            ConstraintViolationListNormalizer::PAYLOAD_FIELDS => null,
        ]];
    }
}
