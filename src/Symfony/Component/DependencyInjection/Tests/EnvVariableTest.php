<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use Symfony\Component\DependencyInjection\EnvVariable;

class EnvVariableTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $ref = new EnvVariable('foo');
        $this->assertEquals('foo', (string) $ref, '__construct() sets the id of the env variable, which is used for the __toString() method');
    }
}
