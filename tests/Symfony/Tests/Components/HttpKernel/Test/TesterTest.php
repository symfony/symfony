<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\HttpKernel\Test;

use Symfony\Components\HttpKernel\Test\Tester;

class TestTester extends Tester
{
    public function getTestCase()
    {
        return $this->test;
    }
}

class TesterTest extends \PHPUnit_Framework_TestCase
{
    public function testSetTestCase()
    {
        $tester = new TestTester();
        $tester->setTestCase($this);

        $this->assertSame($this, $tester->getTestCase(), '->setTestCase() sets the test case object associated with the tester');
    }
}
