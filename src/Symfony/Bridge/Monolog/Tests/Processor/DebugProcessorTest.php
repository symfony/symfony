<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;

class DebugProcessorTest extends TestCase
{
    public function testNoChannelsAreFilteredByDefault()
    {
        $handler = new TestHandler();
        $processor = new DebugProcessor();

        $aLogger = new Logger('a', array($handler), array($processor));
        $bLogger = new Logger('b', array($handler), array($processor));

        $aLogger->info('test A');
        $bLogger->info('test B');

        $logs = $processor->getLogs();
        $this->assertCount(2, $logs);

        $this->assertEquals('a', $logs[0]['channel']);
        $this->assertEquals('test A', $logs[0]['message']);

        $this->assertEquals('b', $logs[1]['channel']);
        $this->assertEquals('test B', $logs[1]['message']);
    }

    public function testExcludedChannels()
    {
        $handler = new TestHandler();

        $processor = new DebugProcessor();
        $processor->setFilterChannels(array('a'), true);

        $aLogger = new Logger('a', array($handler), array($processor));
        $bLogger = new Logger('b', array($handler), array($processor));

        $aLogger->info('test A');
        $bLogger->info('test B');

        $logs = $processor->getLogs();
        $this->assertCount(1, $logs);

        $this->assertEquals('b', $logs[0]['channel']);
        $this->assertEquals('test B', $logs[0]['message']);
    }

    public function testIncludedChannels()
    {
        $handler = new TestHandler();

        $processor = new DebugProcessor();
        $processor->setFilterChannels(array('a'), false);

        $aLogger = new Logger('a', array($handler), array($processor));
        $bLogger = new Logger('b', array($handler), array($processor));

        $aLogger->info('test A');
        $bLogger->info('test B');

        $logs = $processor->getLogs();
        $this->assertCount(1, $logs);

        $this->assertEquals('a', $logs[0]['channel']);
        $this->assertEquals('test A', $logs[0]['message']);
    }
}
