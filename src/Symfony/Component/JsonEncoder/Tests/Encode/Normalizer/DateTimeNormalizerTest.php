<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Encode\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Encode\Normalizer\DateTimeNormalizer;
use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;

class DateTimeNormalizerTest extends TestCase
{
    public function testNormalize()
    {
        $normalizer = new DateTimeNormalizer();

        $this->assertEquals(
            '2023-07-26T00:00:00+00:00',
            $normalizer->normalize(new \DateTimeImmutable('2023-07-26', new \DateTimeZone('UTC'))),
        );

        $this->assertEquals(
            '26/07/2023 00:00:00',
            $normalizer->normalize((new \DateTimeImmutable('2023-07-26', new \DateTimeZone('UTC')))->setTime(0, 0), [DateTimeNormalizer::FORMAT_KEY => 'd/m/Y H:i:s']),
        );
    }

    public function testThrowWhenInvalidDenormalized()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The denormalized data must implement the "\DateTimeInterface".');

        (new DateTimeNormalizer())->normalize(true);
    }
}
