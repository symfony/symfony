<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\ResettableInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Monolog;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\EnumStub;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

class MonologTest extends TestCase
{
    use VarDumperTestTrait;

    protected function setUp(): void
    {
        $this->setUpVarDumper([
            \ReflectionMethod::class => function (\ReflectionMethod $method, array $a, Stub $stub, bool $isNested) {
                // Obviously, the class is different
                unset($a['class']);

                $extra = $a[Caster::PREFIX_VIRTUAL.'extra']->value;
                // We have to remove comments, because we have some diff like
                // `\DateTimeZone` vs `DateTimeZone`
                unset($extra['line'], $extra['file'], $extra['docComment']);
                $a[Caster::PREFIX_VIRTUAL.'extra'] = new EnumStub($extra, false);

                // Also, return type may vary between self, static, and Logger
                if (\array_key_exists(Caster::PREFIX_VIRTUAL.'returnType', $a)) {
                    $v = $a[Caster::PREFIX_VIRTUAL.'returnType']->value;
                    if (
                        'self' === $v
                        || 'static' === $v
                        || Logger::class === $v
                    ) {
                        unset($a[Caster::PREFIX_VIRTUAL.'returnType']);
                    }
                }

                return $a;
            },
        ]);
    }

    public function testPublicApi()
    {
        $base = new \ReflectionClass(Logger::class);
        foreach ($base->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            // We do not support deprecated methods
            if (in_array($method->getName(), ['getLevelName', 'toMonologLevel'])) {
                continue;
            }
            $symfonyMethod = new \ReflectionMethod(Monolog::class, $method->getName());
            $this->assertDumpEquals($method, $symfonyMethod);
        }

        foreach ($base->getConstants(\ReflectionClassConstant::IS_PUBLIC) as $name => $value) {
            // We do not support deprecated methods
            if (in_array($name, ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY', 'API'])) {
                continue;
            }
            $symfonyConstant = new \ReflectionClassConstant(Monolog::class, $name);
            $this->assertDumpEquals($value, $symfonyConstant->getValue());
        }
    }

    public function testGetLogsWithoutDebugProcessor()
    {
        $handler = new TestHandler();
        $logger = $this->createLogger(__METHOD__, [$handler]);

        $logger->error('error message');
        $this->assertSame([], $logger->getLogs());
    }

    public function testCountErrorsWithoutDebugProcessor()
    {
        $handler = new TestHandler();
        $logger = $this->createLogger(__METHOD__, [$handler]);

        $logger->error('error message');
        $this->assertSame(0, $logger->countErrors());
    }

    public function testGetLogsWithDebugProcessor()
    {
        $handler = new TestHandler();
        $processor = new DebugProcessor();
        $logger = $this->createLogger(__METHOD__, [$handler], [$processor]);

        $logger->error('error message');
        $this->assertCount(1, $logger->getLogs());
    }

    public function testCountErrorsWithDebugProcessor()
    {
        $handler = new TestHandler();
        $processor = new DebugProcessor();
        $logger = $this->createLogger(__METHOD__, [$handler], [$processor]);

        $logger->debug('test message');
        $logger->info('test message');
        $logger->notice('test message');
        $logger->warning('test message');

        $logger->error('test message');
        $logger->critical('test message');
        $logger->alert('test message');
        $logger->emergency('test message');

        $this->assertSame(4, $logger->countErrors());
    }

    public function testGetLogsWithDebugProcessor2()
    {
        $handler = new TestHandler();
        $logger = $this->createLogger('test', [$handler]);
        $logger->pushProcessor(new DebugProcessor());

        $logger->info('test');
        $this->assertCount(1, $logger->getLogs());
        [$record] = $logger->getLogs();

        $this->assertEquals('test', $record['message']);
        $this->assertEquals(200, $record['priority']);
    }

    public function testGetLogsWithDebugProcessor3()
    {
        $request = new Request();
        $processor = $this->createMock(DebugProcessor::class);
        $processor->expects($this->once())->method('getLogs')->with($request);
        $processor->expects($this->once())->method('countErrors')->with($request);

        $handler = new TestHandler();
        $logger = $this->createLogger('test', [$handler]);
        $logger->pushProcessor($processor);

        $logger->getLogs($request);
        $logger->countErrors($request);
    }

    public function testClear()
    {
        $handler = new TestHandler();
        $logger = $this->createLogger('test', [$handler]);
        $logger->pushProcessor(new DebugProcessor());

        $logger->info('test');
        $logger->clear();

        $this->assertEmpty($logger->getLogs());
        $this->assertSame(0, $logger->countErrors());
    }

    public function testReset()
    {
        $handler = new TestHandler();
        $logger = $this->createLogger('test', [$handler]);
        $logger->pushProcessor(new DebugProcessor());

        $logger->info('test');
        $logger->reset();

        $this->assertEmpty($logger->getLogs());
        $this->assertSame(0, $logger->countErrors());
        if (class_exists(ResettableInterface::class)) {
            $this->assertEmpty($handler->getRecords());
        }
    }

    protected function createLogger($name, array $handlers = [], array $processors = [])
    {
        if (Logger::API <= 2) {
            $this->markTestSkipped('Monolog 3.x is required.');
        }

        return new Monolog($name, $handlers, $processors);
    }
}
