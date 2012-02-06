<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Propel1;

abstract class Propel1TestCase extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!class_exists('\Propel')) {
            $this->markTestSkipped('Propel is not available.');
        }
    }
}
