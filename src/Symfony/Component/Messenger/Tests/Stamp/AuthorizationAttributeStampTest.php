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
use Symfony\Component\Messenger\Stamp\AuthorizationAttributeStamp;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
class AuthorizationAttributeStampTest extends TestCase
{
    public function testStamp()
    {
        $stamp = new AuthorizationAttributeStamp($attribute = 'foo');
        $this->assertSame($attribute, $stamp->getAttribute());
    }

    public function testSerializable()
    {
        $this->assertEquals($stamp = new AuthorizationAttributeStamp('foo'), unserialize(serialize($stamp)));
    }
}
