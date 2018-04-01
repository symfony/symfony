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
use Symphony\Component\DependencyInjection\Parameter;

class ParameterTest extends TestCase
{
    public function testConstructor()
    {
        $ref = new Parameter('foo');
        $this->assertEquals('foo', (string) $ref, '__construct() sets the id of the parameter, which is used for the __toString() method');
    }
}
