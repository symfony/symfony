<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Reference;

class ReferenceTest extends TestCase
{
    public function testConstructor()
    {
        $ref = new Reference('foo');
        $this->assertEquals('foo', (string) $ref, '__construct() sets the id of the reference, which is used for the __toString() method');
    }
}
