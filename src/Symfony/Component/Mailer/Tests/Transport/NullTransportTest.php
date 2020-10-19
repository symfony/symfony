<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\NullTransport;

class NullTransportTest extends TestCase
{
    public function testToString()
    {
        $t = new NullTransport();
        $this->assertEquals('null://', (string) $t);
    }
}
