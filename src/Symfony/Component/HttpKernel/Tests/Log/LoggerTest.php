<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Log;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Log\Logger;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class LoggerTest extends TestCase
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $tmpFile;

    protected function setUp()
    {
        $this->tmpFile = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'log';
        $this->logger = new Logger(LogLevel::DEBUG, $this->tmpFile);
    }

    protected function tearDown()
    {
        if (!@unlink($this->tmpFile)) {
            file_put_contents($this->tmpFile, '');
        }
    }

    public static function assertLogsMatch(array $expected, array $given)
    {
        foreach ($given as $k => $line) {
            self::assertThat(1 === preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}[\+-][0-9]{2}:[0-9]{2} '.preg_quote($expected[$k]).'/', $line), self::isTrue(), "\"$line\" do not match expected pattern \"$expected[$k]\"");
        }
    }

    /**
     * Return the log messages in order.
     *
     * @return string[]
     */
    public function getLogs()
    {
        return file($this->tmpFile, FILE_IGNORE_NEW_LINES);
    }

    public function testImplements()
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }

    /**
     * @dataProvider provideLevelsAndMessages
     */
    public function testLogsAtAllLevels($level, $message)
    {
        $this->logger->{$level}($message, array('user' => 'Bob'));
        $this->logger->log($level, $message, array('user' => 'Bob'));

        $expected = array(
            "[$level] message of level $level with context: Bob",
            "[$level] message of level $level with context: Bob",
        );
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function provideLevelsAndMessages()
    {
        return array(
            LogLevel::EMERGENCY => array(LogLevel::EMERGENCY, 'message of level emergency with context: {user}'),
            LogLevel::ALERT => array(LogLevel::ALERT, 'message of level alert with context: {user}'),
            LogLevel::CRITICAL => array(LogLevel::CRITICAL, 'message of level critical with context: {user}'),
            LogLevel::ERROR => array(LogLevel::ERROR, 'message of level error with context: {user}'),
            LogLevel::WARNING => array(LogLevel::WARNING, 'message of level warning with context: {user}'),
            LogLevel::NOTICE => array(LogLevel::NOTICE, 'message of level notice with context: {user}'),
            LogLevel::INFO => array(LogLevel::INFO, 'message of level info with context: {user}'),
            LogLevel::DEBUG => array(LogLevel::DEBUG, 'message of level debug with context: {user}'),
        );
    }

    public function testLogLevelDisabled()
    {
        $this->logger = new Logger(LogLevel::INFO, $this->tmpFile);

        $this->logger->debug('test', array('user' => 'Bob'));
        $this->logger->log(LogLevel::DEBUG, 'test', array('user' => 'Bob'));

        // Will always be true, but asserts than an exception isn't thrown
        $this->assertSame(array(), $this->getLogs());
    }

    /**
     * @expectedException \Psr\Log\InvalidArgumentException
     */
    public function testThrowsOnInvalidLevel()
    {
        $this->logger->log('invalid level', 'Foo');
    }

    /**
     * @expectedException \Psr\Log\InvalidArgumentException
     */
    public function testThrowsOnInvalidMinLevel()
    {
        new Logger('invalid');
    }

    /**
     * @expectedException \Psr\Log\InvalidArgumentException
     */
    public function testInvalidOutput()
    {
        new Logger(LogLevel::DEBUG, '/');
    }

    public function testContextReplacement()
    {
        $logger = $this->logger;
        $logger->info('{Message {nothing} {user} {foo.bar} a}', array('user' => 'Bob', 'foo.bar' => 'Bar'));

        $expected = array('[info] {Message {nothing} Bob Bar a}');
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testObjectCastToString()
    {
        if (method_exists($this, 'createPartialMock')) {
            $dummy = $this->createPartialMock(DummyTest::class, array('__toString'));
        } else {
            $dummy = $this->getMock(DummyTest::class, array('__toString'));
        }
        $dummy->expects($this->atLeastOnce())
            ->method('__toString')
            ->will($this->returnValue('DUMMY'));

        $this->logger->warning($dummy);

        $expected = array('[warning] DUMMY');
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testContextCanContainAnything()
    {
        $context = array(
            'bool' => true,
            'null' => null,
            'string' => 'Foo',
            'int' => 0,
            'float' => 0.5,
            'nested' => array('with object' => new DummyTest()),
            'object' => new \DateTime(),
            'resource' => fopen('php://memory', 'r'),
        );

        $this->logger->warning('Crazy context data', $context);

        $expected = array('[warning] Crazy context data');
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testContextExceptionKeyCanBeExceptionOrOtherValues()
    {
        $logger = $this->logger;
        $logger->warning('Random message', array('exception' => 'oops'));
        $logger->critical('Uncaught Exception!', array('exception' => new \LogicException('Fail')));

        $expected = array(
            '[warning] Random message',
            '[critical] Uncaught Exception!',
        );
        $this->assertLogsMatch($expected, $this->getLogs());
    }

    public function testFormatter()
    {
        $this->logger = new Logger(LogLevel::DEBUG, $this->tmpFile, function ($level, $message, $context) {
            return json_encode(array('level' => $level, 'message' => $message, 'context' => $context)).\PHP_EOL;
        });

        $this->logger->error('An error', array('foo' => 'bar'));
        $this->logger->warning('A warning', array('baz' => 'bar'));
        $this->assertSame(array(
            '{"level":"error","message":"An error","context":{"foo":"bar"}}',
            '{"level":"warning","message":"A warning","context":{"baz":"bar"}}',
        ), $this->getLogs());
    }
}

class DummyTest
{
    public function __toString()
    {
    }
}
