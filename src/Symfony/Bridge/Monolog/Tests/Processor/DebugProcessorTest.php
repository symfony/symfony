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

use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class DebugProcessorTest extends TestCase
{
    public function testDebugProcessor()
    {
        $processor = new DebugProcessor();
        $processor($this->getRecord());
        $processor($this->getRecord(Logger::ERROR));

        $this->assertCount(2, $processor->getLogs());
        $this->assertSame(1, $processor->countErrors());
    }

    public function testDebugProcessorWithoutLogs()
    {
        $processor = new DebugProcessor();

        $this->assertCount(0, $processor->getLogs());
        $this->assertSame(0, $processor->countErrors());
    }

    public function testWithRequestStack()
    {
        $stack = new RequestStack();
        $processor = new DebugProcessor($stack);
        $processor($this->getRecord());
        $processor($this->getRecord(Logger::ERROR));

        $this->assertCount(2, $processor->getLogs());
        $this->assertSame(1, $processor->countErrors());

        $request = new Request();
        $stack->push($request);

        $processor($this->getRecord());
        $processor($this->getRecord(Logger::ERROR));

        $this->assertCount(4, $processor->getLogs());
        $this->assertSame(2, $processor->countErrors());

        $this->assertCount(2, $processor->getLogs($request));
        $this->assertSame(1, $processor->countErrors($request));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "Symfony\Bridge\Monolog\Processor\DebugProcessor::getLogs()" method will have a new "Request $request = null" argument in version 5.0, not defining it is deprecated since Symfony 4.2.
     */
    public function testInheritedClassCallGetLogsWithoutArgument()
    {
        $debugProcessorChild = new ClassThatInheritDebugProcessor();
        $debugProcessorChild->getLogs();
    }

    /**
     * @group legacy
     * @expectedDeprecation The "Symfony\Bridge\Monolog\Processor\DebugProcessor::countErrors()" method will have a new "Request $request = null" argument in version 5.0, not defining it is deprecated since Symfony 4.2.
     */
    public function testInheritedClassCallCountErrorsWithoutArgument()
    {
        $debugProcessorChild = new ClassThatInheritDebugProcessor();
        $debugProcessorChild->countErrors();
    }

    private function getRecord($level = Logger::WARNING, $message = 'test')
    {
        return array(
            'message' => $message,
            'context' => array(),
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => new \DateTime(),
            'extra' => array(),
        );
    }
}

class ClassThatInheritDebugProcessor extends DebugProcessor
{
    public function getLogs()
    {
        parent::getLogs();
    }

    public function countErrors()
    {
        parent::countErrors();
    }
}
