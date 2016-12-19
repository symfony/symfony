<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Helper;

use Symfony\Bundle\FrameworkBundle\Templating\Helper\StopwatchHelper;

class StopwatchHelperTest extends \PHPUnit_Framework_TestCase
{
    public function testDevEnvironment()
    {
        $stopwatch = $this->getMockBuilder('Symfony\Component\Stopwatch\Stopwatch')->getMock();
        $stopwatch->expects($this->once())
            ->method('start')
            ->with('foo');

        $helper = new StopwatchHelper($stopwatch);
        $helper->start('foo');
    }

    public function testProdEnvironment()
    {
        $helper = new StopwatchHelper(null);

        try {
            $helper->start('foo');
        } catch (\BadMethodCallException $e) {
            $this->fail('Assumed stopwatch is not called when not provided');
        }
    }
}
