<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    /**
     * @requires PHPUnit < 6.0
     */
    public function testAliasingOfErrorClasses()
    {
        $this->assertInstanceOf(
            \PHPUnit_Framework_Error::class,
            new \PHPUnit\Framework\Error\Error('message', 0, __FILE__, __LINE__)
        );
        $this->assertInstanceOf(
            \PHPUnit_Framework_Error_Deprecated::class,
            new \PHPUnit\Framework\Error\Deprecated('message', 0, __FILE__, __LINE__)
        );
        $this->assertInstanceOf(
            \PHPUnit_Framework_Error_Notice::class,
            new \PHPUnit\Framework\Error\Notice('message', 0, __FILE__, __LINE__)
        );
        $this->assertInstanceOf(
            \PHPUnit_Framework_Error_Warning::class,
            new \PHPUnit\Framework\Error\Warning('message', 0, __FILE__, __LINE__)
        );
    }
}
