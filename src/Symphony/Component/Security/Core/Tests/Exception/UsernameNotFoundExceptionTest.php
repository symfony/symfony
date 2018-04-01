<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Exception\UsernameNotFoundException;

class UsernameNotFoundExceptionTest extends TestCase
{
    public function testGetMessageData()
    {
        $exception = new UsernameNotFoundException('Username could not be found.');
        $this->assertEquals(array('{{ username }}' => null), $exception->getMessageData());
        $exception->setUsername('username');
        $this->assertEquals(array('{{ username }}' => 'username'), $exception->getMessageData());
    }
}
