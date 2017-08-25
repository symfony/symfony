<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class CustomUserMessageAuthenticationExceptionTest extends TestCase
{
    public function testConstructWithSAfeMessage()
    {
        $e = new CustomUserMessageAuthenticationException('SAFE MESSAGE', array('foo' => true));

        $this->assertEquals('SAFE MESSAGE', $e->getMessageKey());
        $this->assertEquals(array('foo' => true), $e->getMessageData());
        $this->assertEquals('SAFE MESSAGE', $e->getMessage());
    }
}
