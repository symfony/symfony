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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\Helper\StopwatchHelper;

/**
 * @group legacy
 */
class StopwatchHelperTest extends TestCase
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
        $helper->start('foo');

        // add a dummy assertion here to satisfy PHPUnit, the only thing we want to test is that the code above
        // can be executed without throwing any exceptions
        $this->addToAssertionCount(1);
    }
}
