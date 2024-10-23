<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Decode\Denormalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Decode\Denormalizer\DateTimeDenormalizer;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;

class DateTimeDenormalizerTest extends TestCase
{
    public function testDenormalizeImmutable()
    {
        $denormalizer = new DateTimeDenormalizer(immutable: true);

        $this->assertEquals(
            new \DateTimeImmutable('2023-07-26'),
            $denormalizer->denormalize('2023-07-26', []),
        );

        $this->assertEquals(
            (new \DateTimeImmutable('2023-07-26'))->setTime(0, 0),
            $denormalizer->denormalize('26/07/2023 00:00:00', [DateTimeDenormalizer::FORMAT_KEY => 'd/m/Y H:i:s']),
        );
    }

    public function testDenormalizeMutable()
    {
        $denormalizer = new DateTimeDenormalizer(immutable: false);

        $this->assertEquals(
            new \DateTime('2023-07-26'),
            $denormalizer->denormalize('2023-07-26', []),
        );

        $this->assertEquals(
            (new \DateTime('2023-07-26'))->setTime(0, 0),
            $denormalizer->denormalize('26/07/2023 00:00:00', [DateTimeDenormalizer::FORMAT_KEY => 'd/m/Y H:i:s']),
        );
    }

    public function testThrowWhenInvalidNormalized()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The normalized data is either not an string, or an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.');

        (new DateTimeDenormalizer(immutable: true))->denormalize(true, []);
    }

    public function testThrowWhenInvalidDateTimeString()
    {
        $denormalizer = new DateTimeDenormalizer(immutable: true);

        try {
            $denormalizer->denormalize('0', []);
            $this->fail(\sprintf('A "%s" exception must have been thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Parsing datetime string \"0\" resulted in 1 errors: \nat position 0: Unexpected character", $e->getMessage());
        }

        try {
            $denormalizer->denormalize('0', [DateTimeDenormalizer::FORMAT_KEY => 'Y-m-d']);
            $this->fail(\sprintf('A "%s" exception must have been thrown.', InvalidArgumentException::class));
        } catch (InvalidArgumentException $e) {
            $this->assertEquals("Parsing datetime string \"0\" using format \"Y-m-d\" resulted in 1 errors: \nat position 1: Not enough data available to satisfy format", $e->getMessage());
        }
    }
}
