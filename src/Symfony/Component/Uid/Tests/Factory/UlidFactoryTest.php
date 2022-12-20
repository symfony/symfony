<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Factory\UlidFactory;

final class UlidFactoryTest extends TestCase
{
    public function testCreate()
    {
        $ulidFactory = new UlidFactory();

        $ulidFactory->create();

        $ulid1 = $ulidFactory->create(new \DateTime('@999999.123000'));
        self::assertSame('999999.123000', $ulid1->getDateTime()->format('U.u'));
        $ulid2 = $ulidFactory->create(new \DateTime('@999999.123000'));
        self::assertSame('999999.123000', $ulid2->getDateTime()->format('U.u'));

        self::assertFalse($ulid1->equals($ulid2));
        self::assertSame(-1, $ulid1->compare($ulid2));

        $ulid3 = $ulidFactory->create(new \DateTime('@1234.162524'));
        self::assertSame('1234.162000', $ulid3->getDateTime()->format('U.u'));
    }

    public function testCreateWithInvalidTimestamp()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The timestamp must be positive.');

        (new UlidFactory())->create(new \DateTime('@-1000'));
    }
}
