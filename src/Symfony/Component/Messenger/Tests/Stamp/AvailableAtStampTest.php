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

use DateTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\AvailableAtStamp;

/**
 * @author Antonio del Olmo García <adelolmog@gmail.com>
 */
class AvailableAtStampTest extends TestCase
{
    public function testStamp()
    {
        $stamp = new AvailableAtStamp($availableAt = new DateTime());
        $this->assertSame($availableAt, $stamp->getAvailableAt());
    }
}
