<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Routing\CompiledRoute;

class CompiledRouteTest extends TestCase
{
    public function testAccessors()
    {
        $compiled = new CompiledRoute('prefix', 'regex', array('tokens'), array(), null, array(), array(), array('variables'));
        $this->assertEquals('prefix', $compiled->getStaticPrefix(), '__construct() takes a static prefix as its second argument');
        $this->assertEquals('regex', $compiled->getRegex(), '__construct() takes a regexp as its third argument');
        $this->assertEquals(array('tokens'), $compiled->getTokens(), '__construct() takes an array of tokens as its fourth argument');
        $this->assertEquals(array('variables'), $compiled->getVariables(), '__construct() takes an array of variables as its ninth argument');
    }
}
