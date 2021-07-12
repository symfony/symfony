<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\PriorityStamp;

/**
 * @author Valentin Nazarov <i.kozlice@protonmail.com>
 */
class PriorityStampTest extends TestCase
{
    /**
     * @dataProvider invalidPriorityProvider
     */
    public function testConstructorFailsOnPriorityOutOfBounds(int $priority)
    {
        $this->expectException(InvalidArgumentException::class);
        new PriorityStamp($priority);
    }

    public function invalidPriorityProvider(): iterable
    {
        yield [PriorityStamp::MIN_PRIORITY - 1];
        yield [PriorityStamp::MAX_PRIORITY + 1];
    }
}
