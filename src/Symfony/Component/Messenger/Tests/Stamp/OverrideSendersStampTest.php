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
use Symfony\Component\Messenger\Stamp\OverrideSendersStamp;

class OverrideSendersStampTest extends TestCase
{
    public function testGetSenders()
    {
        $stamp = new OverrideSendersStamp(['other_transport']);
        $this->assertSame(['other_transport'], $stamp->getSenders());
    }
}
