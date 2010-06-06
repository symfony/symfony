<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Framework\PropelBundle\Tests;

class TestCase extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!file_exists($file = __DIR__.'/../../../../../vendor/propel/runtime/lib/Propel.php')) {
            $this->markTestSkipped('Propel is not available.');
        }

        require_once $file;
    }
}
