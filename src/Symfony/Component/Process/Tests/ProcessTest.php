<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Pipes\PipesInterface;
use Symfony\Component\Process\Process;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
class ProcessTest extends TestCase
{
    private static $phpBin;
    private static $process;
    private static $sigchild;

    public static function setUpBeforeClass(): void
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === \PHP_SAPI ? 'php' : $phpBin->find());

        ob_start();
        phpinfo(INFO_GENERAL);
        self::$sigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
    }

    protected function tearDown(): void
    {
        if (self::$process) {
            self::$process->stop(0);
            self::$process = null;
        }
    }

    public function testInvalidCwd()
    {
        $this->expectException('Symfony\Component\Process\Exception\RuntimeException');
        $this->expectExceptionMessageRegExp('/The provided cwd ".*" does not exist\./');
        try {
            // Check that it works fine if the CWD exists
            $cmd = new Process(['echo', 'test'], __DIR__);
            $cmd->run();
        } catch (\Exception $e) {
            $this->fail($e);
        }

        $cmd = new Process(['echo', 'test'], __DIR__.'/notfound/');
        $cmd->run();
    }

    public function testThatProcessDoesNotThrowWarningDuringRun()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is transient on Windows');
        }
        @trigger_error('Test Error', E_USER_NOTICE);
        $process = $this->getProcessForCode('sleep(3)');
        $process->run();
        $actualError = error_get_last();
        $this->assertEquals('Test Error', $actualError['message']);
        $this->assertEquals(E_USER_NOTICE, $actualError['type']);
    }

    public function testNegativeTimeoutFromConstructor()
    {
        $this->expectException('Symfony\Component\Process\Exception\InvalidArgumentException');
        $this->getProcess('', null, null, null, -1);
    }

    public function testNegativeTimeoutFromSetter()
    {
        $this->expectException('Symfony\Component\Process\Exception\InvalidArgumentException');
        $p = $this->getProcess('');
        $p->setTimeout(-1);
    }

    public function testFloatAndNullTimeout()
    {
        $p = $this->getProcess('');

        $p->setTimeout(10);
        $this->assertSame(10.0, $p->getTimeout());

        $p->setTimeout(null);
        $this->assertNull($p->getTimeout());

        $p->setTimeout(0.0);
        $this->assertNull($p->getTimeout());
    }

    /**
     * @requires extension pcntl
     */
    public function testStopWithTimeoutIsActuallyWorking()
    {
        $p = $this->getProcess([self::$phpBin, __DIR__.'/NonStopableProcess.php', 30]);
        $p->start();

        while ($p->isRunning() && false === strpos($p->getOutput(), 'received')) {
            usleep(1000);
        }

        if (!$p->isRunning()) {
            throw new \LogicException('Process is not running: '.$p->getErrorOutput());
        }

        $start = microtime(true);
        $p->stop(0.1);

        $p->wait();

        $this->assertLessThan(15, microtime(true) - $start);
    }

    public function testWaitUntilSpecificOutput()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestIncomplete('This test is too transient on Windows, help wanted to improve it');
        }

        $p = $this->getProcess([self::$phpBin, __DIR__.'/KillableProcessWithOutput.php']);
        $p->start();

        $start = microtime(true);

        $completeOutput = '';
        $result = $p->waitUntil(function ($type, $output) use (&$completeOutput) {
            return false !== strpos($completeOutput .= $output, 'One more');
        });
        $this->assertTrue($result);
        $this->assertLessThan(20, microtime(true) - $start);
        $this->assertStringStartsWith("First iteration output\nSecond iteration output\nOne more", $completeOutput);
        $p->stop();
    }

    public function testWaitUntilCanReturnFalse()
    {
        $p = $this->getProcess('echo foo');
        $p->start();
        $this->assertFalse($p->waitUntil(function () { return false; }));
    }

    public function testAllOutputIsActuallyReadOnTermination()
    {
        // this code will result in a maximum of 2 reads of 8192 bytes by calling
        // start() and isRunning().  by the time getOutput() is called the process
        // has terminated so the internal pipes array is already empty. normally
        // the call to start() will not read any data as the process will not have
        // generated output, but this is non-deterministic so we must count it as
        // a possibility.  therefore we need 2 * PipesInterface::CHUNK_SIZE plus
        // another byte which will never be read.
        $expectedOutputSize = PipesInterface::CHUNK_SIZE * 2 + 2;

        $code = sprintf('echo str_repeat(\'*\', %d);', $expectedOutputSize);
        $p = $this->getProcessForCode($code);

        $p->start();

        // Don't call Process::run nor Process::wait to avoid any read of pipes
        $h = new \ReflectionProperty($p, 'process');
        $h->setAccessible(true);
        $h = $h->getValue($p);
        $s = @proc_get_status($h);

        while (!empty($s['running'])) {
            usleep(1000);
            $s = proc_get_status($h);
        }

        $o = $p->getOutput();

        $this->assertEquals($expectedOutputSize, \strlen($o));
    }

    public function testCallbacksAreExecutedWithStart()
    {
        $process = $this->getProcess('echo foo');
        $process->start(function ($type, $buffer) use (&$data) {
            $data .= $buffer;
        });

        $process->wait();

        $this->assertSame('foo'.PHP_EOL, $data);
    }

    /**
     * tests results from sub processes.
     *
     * @dataProvider responsesCodeProvider
     */
    public function testProcessResponses($expected, $getter, $code)
    {
        $p = $this->getProcessForCode($code);
        $p->run();

        $this->assertSame($expected, $p->$getter());
    }

    /**
     * tests results from sub processes.
     *
     * @dataProvider pipesCodeProvider
     */
    public function testProcessPipes($code, $size)
    {
        $expected = str_repeat(str_repeat('*', 1024), $size).'!';
        $expectedLength = (1024 * $size) + 1;

        $p = $this->getProcessForCode($code);
        $p->setInput($expected);
        $p->run();

        $this->assertEquals($expectedLength, \strlen($p->getOutput()));
        $this->assertEquals($expectedLength, \strlen($p->getErrorOutput()));
    }

    /**
     * @dataProvider pipesCodeProvider
     */
    public function testSetStreamAsInput($code, $size)
    {
        $expected = str_repeat(str_repeat('*', 1024), $size).'!';
        $expectedLength = (1024 * $size) + 1;

        $stream = fopen('php://temporary', 'w+');
        fwrite($stream, $expected);
        rewind($stream);

        $p = $this->getProcessForCode($code);
        $p->setInput($stream);
        $p->run();

        fclose($stream);

        $this->assertEquals($expectedLength, \strlen($p->getOutput()));
        $this->assertEquals($expectedLength, \strlen($p->getErrorOutput()));
    }

    public function testLiveStreamAsInput()
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'hello');
        rewind($stream);

        $p = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);');
        $p->setInput($stream);
        $p->start(function ($type, $data) use ($stream) {
            if ('hello' === $data) {
                fclose($stream);
            }
        });
        $p->wait();

        $this->assertSame('hello', $p->getOutput());
    }

    public function testSetInputWhileRunningThrowsAnException()
    {
        $this->expectException('Symfony\Component\Process\Exception\LogicException');
        $this->expectExceptionMessage('Input can not be set while the process is running.');
        $process = $this->getProcessForCode('sleep(30);');
        $process->start();
        try {
            $process->setInput('foobar');
            $process->stop();
            $this->fail('A LogicException should have been raised.');
        } catch (LogicException $e) {
        }
        $process->stop();

        throw $e;
    }

    /**
     * @dataProvider provideInvalidInputValues
     */
    public function testInvalidInput($value)
    {
        $this->expectException('Symfony\Component\Process\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Symfony\Component\Process\Process::setInput only accepts strings, Traversable objects or stream resources.');
        $process = $this->getProcess('foo');
        $process->setInput($value);
    }

    public function provideInvalidInputValues()
    {
        return [
            [[]],
            [new NonStringifiable()],
        ];
    }

    /**
     * @dataProvider provideInputValues
     */
    public function testValidInput($expected, $value)
    {
        $process = $this->getProcess('foo');
        $process->setInput($value);
        $this->assertSame($expected, $process->getInput());
    }

    public function provideInputValues()
    {
        return [
            [null, null],
            ['24.5', 24.5],
            ['input data', 'input data'],
        ];
    }

    public function chainedCommandsOutputProvider()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            return [
                ["2 \r\n2\r\n", '&&', '2'],
            ];
        }

        return [
            ["1\n1\n", ';', '1'],
            ["2\n2\n", '&&', '2'],
        ];
    }

    /**
     * @dataProvider chainedCommandsOutputProvider
     */
    public function testChainedCommandsOutput($expected, $operator, $input)
    {
        $process = $this->getProcess(sprintf('echo %s %s echo %s', $input, $operator, $input));
        $process->run();
        $this->assertEquals($expected, $process->getOutput());
    }

    public function testCallbackIsExecutedForOutput()
    {
        $p = $this->getProcessForCode('echo \'foo\';');

        $called = false;
        $p->run(function ($type, $buffer) use (&$called) {
            $called = 'foo' === $buffer;
        });

        $this->assertTrue($called, 'The callback should be executed with the output');
    }

    public function testCallbackIsExecutedForOutputWheneverOutputIsDisabled()
    {
        $p = $this->getProcessForCode('echo \'foo\';');
        $p->disableOutput();

        $called = false;
        $p->run(function ($type, $buffer) use (&$called) {
            $called = 'foo' === $buffer;
        });

        $this->assertTrue($called, 'The callback should be executed with the output');
    }

    public function testGetErrorOutput()
    {
        $p = $this->getProcessForCode('$n = 0; while ($n < 3) { file_put_contents(\'php://stderr\', \'ERROR\'); $n++; }');

        $p->run();
        $this->assertEquals(3, preg_match_all('/ERROR/', $p->getErrorOutput(), $matches));
    }

    public function testFlushErrorOutput()
    {
        $p = $this->getProcessForCode('$n = 0; while ($n < 3) { file_put_contents(\'php://stderr\', \'ERROR\'); $n++; }');

        $p->run();
        $p->clearErrorOutput();
        $this->assertEmpty($p->getErrorOutput());
    }

    /**
     * @dataProvider provideIncrementalOutput
     */
    public function testIncrementalOutput($getOutput, $getIncrementalOutput, $uri)
    {
        $lock = tempnam(sys_get_temp_dir(), __FUNCTION__);

        $p = $this->getProcessForCode('file_put_contents($s = \''.$uri.'\', \'foo\'); flock(fopen('.var_export($lock, true).', \'r\'), LOCK_EX); file_put_contents($s, \'bar\');');

        $h = fopen($lock, 'w');
        flock($h, LOCK_EX);

        $p->start();

        foreach (['foo', 'bar'] as $s) {
            while (false === strpos($p->$getOutput(), $s)) {
                usleep(1000);
            }

            $this->assertSame($s, $p->$getIncrementalOutput());
            $this->assertSame('', $p->$getIncrementalOutput());

            flock($h, LOCK_UN);
        }

        fclose($h);
    }

    public function provideIncrementalOutput()
    {
        return [
            ['getOutput', 'getIncrementalOutput', 'php://stdout'],
            ['getErrorOutput', 'getIncrementalErrorOutput', 'php://stderr'],
        ];
    }

    public function testGetOutput()
    {
        $p = $this->getProcessForCode('$n = 0; while ($n < 3) { echo \' foo \'; $n++; }');

        $p->run();
        $this->assertEquals(3, preg_match_all('/foo/', $p->getOutput(), $matches));
    }

    public function testFlushOutput()
    {
        $p = $this->getProcessForCode('$n=0;while ($n<3) {echo \' foo \';$n++;}');

        $p->run();
        $p->clearOutput();
        $this->assertEmpty($p->getOutput());
    }

    public function testZeroAsOutput()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            // see http://stackoverflow.com/questions/7105433/windows-batch-echo-without-new-line
            $p = $this->getProcess('echo | set /p dummyName=0');
        } else {
            $p = $this->getProcess('printf 0');
        }

        $p->run();
        $this->assertSame('0', $p->getOutput());
    }

    public function testExitCodeCommandFailed()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX exit code');
        }

        // such command run in bash return an exitcode 127
        $process = $this->getProcess('nonexistingcommandIhopeneversomeonewouldnameacommandlikethis');
        $process->run();

        $this->assertGreaterThan(0, $process->getExitCode());
    }

    public function testTTYCommand()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not have /dev/tty support');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null && '.$this->getProcessForCode('usleep(100000);')->getCommandLine());
        $process->setTty(true);
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->wait();

        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
    }

    public function testTTYCommandExitCode()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does have /dev/tty support');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTty(true);
        $process->run();

        $this->assertTrue($process->isSuccessful());
    }

    public function testTTYInWindowsEnvironment()
    {
        $this->expectException('Symfony\Component\Process\Exception\RuntimeException');
        $this->expectExceptionMessage('TTY mode is not supported on Windows platform.');
        if ('\\' !== \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is for Windows platform only');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTty(false);
        $process->setTty(true);
    }

    public function testExitCodeTextIsNullWhenExitCodeIsNull()
    {
        $process = $this->getProcess('');
        $this->assertNull($process->getExitCodeText());
    }

    public function testPTYCommand()
    {
        if (!Process::isPtySupported()) {
            $this->markTestSkipped('PTY is not supported on this operating system.');
        }

        $process = $this->getProcess('echo "foo"');
        $process->setPty(true);
        $process->run();

        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
        $this->assertEquals("foo\r\n", $process->getOutput());
    }

    public function testMustRun()
    {
        $process = $this->getProcess('echo foo');

        $this->assertSame($process, $process->mustRun());
        $this->assertEquals('foo'.PHP_EOL, $process->getOutput());
    }

    public function testSuccessfulMustRunHasCorrectExitCode()
    {
        $process = $this->getProcess('echo foo')->mustRun();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testMustRunThrowsException()
    {
        $this->expectException('Symfony\Component\Process\Exception\ProcessFailedException');
        $process = $this->getProcess('exit 1');
        $process->mustRun();
    }

    public function testExitCodeText()
    {
        $process = $this->getProcess('');
        $r = new \ReflectionObject($process);
        $p = $r->getProperty('exitcode');
        $p->setAccessible(true);

        $p->setValue($process, 2);
        $this->assertEquals('Misuse of shell builtins', $process->getExitCodeText());
    }

    public function testStartIsNonBlocking()
    {
        $process = $this->getProcessForCode('usleep(500000);');
        $start = microtime(true);
        $process->start();
        $end = microtime(true);
        $this->assertLessThan(0.4, $end - $start);
        $process->stop();
    }

    public function testUpdateStatus()
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertGreaterThan(0, \strlen($process->getOutput()));
    }

    public function testGetExitCodeIsNullOnStart()
    {
        $process = $this->getProcessForCode('usleep(100000);');
        $this->assertNull($process->getExitCode());
        $process->start();
        $this->assertNull($process->getExitCode());
        $process->wait();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetExitCodeIsNullOnWhenStartingAgain()
    {
        $process = $this->getProcessForCode('usleep(100000);');
        $process->run();
        $this->assertEquals(0, $process->getExitCode());
        $process->start();
        $this->assertNull($process->getExitCode());
        $process->wait();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetExitCode()
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertSame(0, $process->getExitCode());
    }

    public function testStatus()
    {
        $process = $this->getProcessForCode('usleep(100000);');
        $this->assertFalse($process->isRunning());
        $this->assertFalse($process->isStarted());
        $this->assertFalse($process->isTerminated());
        $this->assertSame(Process::STATUS_READY, $process->getStatus());
        $process->start();
        $this->assertTrue($process->isRunning());
        $this->assertTrue($process->isStarted());
        $this->assertFalse($process->isTerminated());
        $this->assertSame(Process::STATUS_STARTED, $process->getStatus());
        $process->wait();
        $this->assertFalse($process->isRunning());
        $this->assertTrue($process->isStarted());
        $this->assertTrue($process->isTerminated());
        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
    }

    public function testStop()
    {
        $process = $this->getProcessForCode('sleep(31);');
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop();
        $this->assertFalse($process->isRunning());
    }

    public function testIsSuccessful()
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertTrue($process->isSuccessful());
    }

    public function testIsSuccessfulOnlyAfterTerminated()
    {
        $process = $this->getProcessForCode('usleep(100000);');
        $process->start();

        $this->assertFalse($process->isSuccessful());

        $process->wait();

        $this->assertTrue($process->isSuccessful());
    }

    public function testIsNotSuccessful()
    {
        $process = $this->getProcessForCode('throw new \Exception(\'BOUM\');');
        $process->run();
        $this->assertFalse($process->isSuccessful());
    }

    public function testProcessIsNotSignaled()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertFalse($process->hasBeenSignaled());
    }

    public function testProcessWithoutTermSignal()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertEquals(0, $process->getTermSignal());
    }

    public function testProcessIsSignaledIfStopped()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $process = $this->getProcessForCode('sleep(32);');
        $process->start();
        $process->stop();
        $this->assertTrue($process->hasBeenSignaled());
        $this->assertEquals(15, $process->getTermSignal()); // SIGTERM
    }

    public function testProcessThrowsExceptionWhenExternallySignaled()
    {
        $this->expectException('Symfony\Component\Process\Exception\ProcessSignaledException');
        $this->expectExceptionMessage('The process has been signaled with signal "9".');
        if (!\function_exists('posix_kill')) {
            $this->markTestSkipped('Function posix_kill is required.');
        }

        if (self::$sigchild) {
            $this->markTestSkipped('PHP is compiled with --enable-sigchild.');
        }

        $process = $this->getProcessForCode('sleep(32.1);');
        $process->start();
        posix_kill($process->getPid(), 9); // SIGKILL

        $process->wait();
    }

    public function testRestart()
    {
        $process1 = $this->getProcessForCode('echo getmypid();');
        $process1->run();
        $process2 = $process1->restart();

        $process2->wait(); // wait for output

        // Ensure that both processed finished and the output is numeric
        $this->assertFalse($process1->isRunning());
        $this->assertFalse($process2->isRunning());
        $this->assertIsNumeric($process1->getOutput());
        $this->assertIsNumeric($process2->getOutput());

        // Ensure that restart returned a new process by check that the output is different
        $this->assertNotEquals($process1->getOutput(), $process2->getOutput());
    }

    public function testRunProcessWithTimeout()
    {
        $this->expectException('Symfony\Component\Process\Exception\ProcessTimedOutException');
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(30);');
        $process->setTimeout(0.1);
        $start = microtime(true);
        try {
            $process->run();
            $this->fail('A RuntimeException should have been raised');
        } catch (RuntimeException $e) {
        }

        $this->assertLessThan(15, microtime(true) - $start);

        throw $e;
    }

    public function testIterateOverProcessWithTimeout()
    {
        $this->expectException('Symfony\Component\Process\Exception\ProcessTimedOutException');
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(30);');
        $process->setTimeout(0.1);
        $start = microtime(true);
        try {
            $process->start();
            foreach ($process as $buffer);
            $this->fail('A RuntimeException should have been raised');
        } catch (RuntimeException $e) {
        }

        $this->assertLessThan(15, microtime(true) - $start);

        throw $e;
    }

    public function testCheckTimeoutOnNonStartedProcess()
    {
        $process = $this->getProcess('echo foo');
        $this->assertNull($process->checkTimeout());
    }

    public function testCheckTimeoutOnTerminatedProcess()
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertNull($process->checkTimeout());
    }

    public function testCheckTimeoutOnStartedProcess()
    {
        $this->expectException('Symfony\Component\Process\Exception\ProcessTimedOutException');
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(33);');
        $process->setTimeout(0.1);

        $process->start();
        $start = microtime(true);

        try {
            while ($process->isRunning()) {
                $process->checkTimeout();
                usleep(100000);
            }
            $this->fail('A ProcessTimedOutException should have been raised');
        } catch (ProcessTimedOutException $e) {
        }

        $this->assertLessThan(15, microtime(true) - $start);

        throw $e;
    }

    public function testIdleTimeout()
    {
        $process = $this->getProcessForCode('sleep(34);');
        $process->setTimeout(60);
        $process->setIdleTimeout(0.1);

        try {
            $process->run();

            $this->fail('A timeout exception was expected.');
        } catch (ProcessTimedOutException $e) {
            $this->assertTrue($e->isIdleTimeout());
            $this->assertFalse($e->isGeneralTimeout());
            $this->assertEquals(0.1, $e->getExceededTimeout());
        }
    }

    public function testIdleTimeoutNotExceededWhenOutputIsSent()
    {
        $process = $this->getProcessForCode('while (true) {echo \'foo \'; usleep(1000);}');
        $process->setTimeout(1);
        $process->start();

        while (false === strpos($process->getOutput(), 'foo')) {
            usleep(1000);
        }

        $process->setIdleTimeout(0.5);

        try {
            $process->wait();
            $this->fail('A timeout exception was expected.');
        } catch (ProcessTimedOutException $e) {
            $this->assertTrue($e->isGeneralTimeout(), 'A general timeout is expected.');
            $this->assertFalse($e->isIdleTimeout(), 'No idle timeout is expected.');
            $this->assertEquals(1, $e->getExceededTimeout());
        }
    }

    public function testStartAfterATimeout()
    {
        $this->expectException('Symfony\Component\Process\Exception\ProcessTimedOutException');
        $this->expectExceptionMessage('exceeded the timeout of 0.1 seconds.');
        $process = $this->getProcessForCode('sleep(35);');
        $process->setTimeout(0.1);

        try {
            $process->run();
            $this->fail('A ProcessTimedOutException should have been raised.');
        } catch (ProcessTimedOutException $e) {
        }
        $this->assertFalse($process->isRunning());
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop(0);

        throw $e;
    }

    public function testGetPid()
    {
        $process = $this->getProcessForCode('sleep(36);');
        $process->start();
        $this->assertGreaterThan(0, $process->getPid());
        $process->stop(0);
    }

    public function testGetPidIsNullBeforeStart()
    {
        $process = $this->getProcess('foo');
        $this->assertNull($process->getPid());
    }

    public function testGetPidIsNullAfterRun()
    {
        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertNull($process->getPid());
    }

    /**
     * @requires extension pcntl
     */
    public function testSignal()
    {
        $process = $this->getProcess([self::$phpBin, __DIR__.'/SignalListener.php']);
        $process->start();

        while (false === strpos($process->getOutput(), 'Caught')) {
            usleep(1000);
        }
        $process->signal(SIGUSR1);
        $process->wait();

        $this->assertEquals('Caught SIGUSR1', $process->getOutput());
    }

    /**
     * @requires extension pcntl
     */
    public function testExitCodeIsAvailableAfterSignal()
    {
        $process = $this->getProcess('sleep 4');
        $process->start();
        $process->signal(SIGKILL);

        while ($process->isRunning()) {
            usleep(10000);
        }

        $this->assertFalse($process->isRunning());
        $this->assertTrue($process->hasBeenSignaled());
        $this->assertFalse($process->isSuccessful());
        $this->assertEquals(137, $process->getExitCode());
    }

    public function testSignalProcessNotRunning()
    {
        $this->expectException('Symfony\Component\Process\Exception\LogicException');
        $this->expectExceptionMessage('Can not send signal on a non running process.');
        $process = $this->getProcess('foo');
        $process->signal(1); // SIGHUP
    }

    /**
     * @dataProvider provideMethodsThatNeedARunningProcess
     */
    public function testMethodsThatNeedARunningProcess($method)
    {
        $process = $this->getProcess('foo');

        $this->expectException('Symfony\Component\Process\Exception\LogicException');
        $this->expectExceptionMessage(sprintf('Process must be started before calling %s.', $method));

        $process->{$method}();
    }

    public function provideMethodsThatNeedARunningProcess()
    {
        return [
            ['getOutput'],
            ['getIncrementalOutput'],
            ['getErrorOutput'],
            ['getIncrementalErrorOutput'],
            ['wait'],
        ];
    }

    /**
     * @dataProvider provideMethodsThatNeedATerminatedProcess
     */
    public function testMethodsThatNeedATerminatedProcess($method)
    {
        $this->expectException('Symfony\Component\Process\Exception\LogicException');
        $this->expectExceptionMessage('Process must be terminated before calling');
        $process = $this->getProcessForCode('sleep(37);');
        $process->start();
        try {
            $process->{$method}();
            $process->stop(0);
            $this->fail('A LogicException must have been thrown');
        } catch (\Exception $e) {
        }
        $process->stop(0);

        throw $e;
    }

    public function provideMethodsThatNeedATerminatedProcess()
    {
        return [
            ['hasBeenSignaled'],
            ['getTermSignal'],
            ['hasBeenStopped'],
            ['getStopSignal'],
        ];
    }

    public function testWrongSignal()
    {
        $this->expectException('Symfony\Component\Process\Exception\RuntimeException');
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('POSIX signals do not work on Windows');
        }

        $process = $this->getProcessForCode('sleep(38);');
        $process->start();
        try {
            $process->signal(-4);
            $this->fail('A RuntimeException must have been thrown');
        } catch (RuntimeException $e) {
            $process->stop(0);
        }

        throw $e;
    }

    public function testDisableOutputDisablesTheOutput()
    {
        $p = $this->getProcess('foo');
        $this->assertFalse($p->isOutputDisabled());
        $p->disableOutput();
        $this->assertTrue($p->isOutputDisabled());
        $p->enableOutput();
        $this->assertFalse($p->isOutputDisabled());
    }

    public function testDisableOutputWhileRunningThrowsException()
    {
        $this->expectException('Symfony\Component\Process\Exception\RuntimeException');
        $this->expectExceptionMessage('Disabling output while the process is running is not possible.');
        $p = $this->getProcessForCode('sleep(39);');
        $p->start();
        $p->disableOutput();
    }

    public function testEnableOutputWhileRunningThrowsException()
    {
        $this->expectException('Symfony\Component\Process\Exception\RuntimeException');
        $this->expectExceptionMessage('Enabling output while the process is running is not possible.');
        $p = $this->getProcessForCode('sleep(40);');
        $p->disableOutput();
        $p->start();
        $p->enableOutput();
    }

    public function testEnableOrDisableOutputAfterRunDoesNotThrowException()
    {
        $p = $this->getProcess('echo foo');
        $p->disableOutput();
        $p->run();
        $p->enableOutput();
        $p->disableOutput();
        $this->assertTrue($p->isOutputDisabled());
    }

    public function testDisableOutputWhileIdleTimeoutIsSet()
    {
        $this->expectException('Symfony\Component\Process\Exception\LogicException');
        $this->expectExceptionMessage('Output can not be disabled while an idle timeout is set.');
        $process = $this->getProcess('foo');
        $process->setIdleTimeout(1);
        $process->disableOutput();
    }

    public function testSetIdleTimeoutWhileOutputIsDisabled()
    {
        $this->expectException('Symfony\Component\Process\Exception\LogicException');
        $this->expectExceptionMessage('timeout can not be set while the output is disabled.');
        $process = $this->getProcess('foo');
        $process->disableOutput();
        $process->setIdleTimeout(1);
    }

    public function testSetNullIdleTimeoutWhileOutputIsDisabled()
    {
        $process = $this->getProcess('foo');
        $process->disableOutput();
        $this->assertSame($process, $process->setIdleTimeout(null));
    }

    /**
     * @dataProvider provideOutputFetchingMethods
     */
    public function testGetOutputWhileDisabled($fetchMethod)
    {
        $this->expectException('Symfony\Component\Process\Exception\LogicException');
        $this->expectExceptionMessage('Output has been disabled.');
        $p = $this->getProcessForCode('sleep(41);');
        $p->disableOutput();
        $p->start();
        $p->{$fetchMethod}();
    }

    public function provideOutputFetchingMethods()
    {
        return [
            ['getOutput'],
            ['getIncrementalOutput'],
            ['getErrorOutput'],
            ['getIncrementalErrorOutput'],
        ];
    }

    public function testStopTerminatesProcessCleanly()
    {
        $process = $this->getProcessForCode('echo 123; sleep(42);');
        $process->run(function () use ($process) {
            $process->stop();
        });
        $this->assertTrue(true, 'A call to stop() is not expected to cause wait() to throw a RuntimeException');
    }

    public function testKillSignalTerminatesProcessCleanly()
    {
        $process = $this->getProcessForCode('echo 123; sleep(43);');
        $process->run(function () use ($process) {
            $process->signal(9); // SIGKILL
        });
        $this->assertTrue(true, 'A call to signal() is not expected to cause wait() to throw a RuntimeException');
    }

    public function testTermSignalTerminatesProcessCleanly()
    {
        $process = $this->getProcessForCode('echo 123; sleep(44);');
        $process->run(function () use ($process) {
            $process->signal(15); // SIGTERM
        });
        $this->assertTrue(true, 'A call to signal() is not expected to cause wait() to throw a RuntimeException');
    }

    public function responsesCodeProvider()
    {
        return [
            //expected output / getter / code to execute
            // [1,'getExitCode','exit(1);'],
            // [true,'isSuccessful','exit();'],
            ['output', 'getOutput', 'echo \'output\';'],
        ];
    }

    public function pipesCodeProvider()
    {
        $variations = [
            'fwrite(STDOUT, $in = file_get_contents(\'php://stdin\')); fwrite(STDERR, $in);',
            'include \''.__DIR__.'/PipeStdinInStdoutStdErrStreamSelect.php\';',
        ];

        if ('\\' === \DIRECTORY_SEPARATOR) {
            // Avoid XL buffers on Windows because of https://bugs.php.net/65650
            $sizes = [1, 2, 4, 8];
        } else {
            $sizes = [1, 16, 64, 1024, 4096];
        }

        $codes = [];
        foreach ($sizes as $size) {
            foreach ($variations as $code) {
                $codes[] = [$code, $size];
            }
        }

        return $codes;
    }

    /**
     * @dataProvider provideVariousIncrementals
     */
    public function testIncrementalOutputDoesNotRequireAnotherCall($stream, $method)
    {
        $process = $this->getProcessForCode('$n = 0; while ($n < 3) { file_put_contents(\''.$stream.'\', $n, 1); $n++; usleep(1000); }', null, null, null, null);
        $process->start();
        $result = '';
        $limit = microtime(true) + 3;
        $expected = '012';

        while ($result !== $expected && microtime(true) < $limit) {
            $result .= $process->$method();
        }

        $this->assertSame($expected, $result);
        $process->stop();
    }

    public function provideVariousIncrementals()
    {
        return [
            ['php://stdout', 'getIncrementalOutput'],
            ['php://stderr', 'getIncrementalErrorOutput'],
        ];
    }

    public function testIteratorInput()
    {
        $input = function () {
            yield 'ping';
            yield 'pong';
        };

        $process = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);', null, null, $input());
        $process->run();
        $this->assertSame('pingpong', $process->getOutput());
    }

    public function testSimpleInputStream()
    {
        $input = new InputStream();

        $process = $this->getProcessForCode('echo \'ping\'; echo fread(STDIN, 4); echo fread(STDIN, 4);');
        $process->setInput($input);

        $process->start(function ($type, $data) use ($input) {
            if ('ping' === $data) {
                $input->write('pang');
            } elseif (!$input->isClosed()) {
                $input->write('pong');
                $input->close();
            }
        });

        $process->wait();
        $this->assertSame('pingpangpong', $process->getOutput());
    }

    public function testInputStreamWithCallable()
    {
        $i = 0;
        $stream = fopen('php://memory', 'w+');
        $stream = function () use ($stream, &$i) {
            if ($i < 3) {
                rewind($stream);
                fwrite($stream, ++$i);
                rewind($stream);

                return $stream;
            }

            return null;
        };

        $input = new InputStream();
        $input->onEmpty($stream);
        $input->write($stream());

        $process = $this->getProcessForCode('echo fread(STDIN, 3);');
        $process->setInput($input);
        $process->start(function ($type, $data) use ($input) {
            $input->close();
        });

        $process->wait();
        $this->assertSame('123', $process->getOutput());
    }

    public function testInputStreamWithGenerator()
    {
        $input = new InputStream();
        $input->onEmpty(function ($input) {
            yield 'pong';
            $input->close();
        });

        $process = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);');
        $process->setInput($input);
        $process->start();
        $input->write('ping');
        $process->wait();
        $this->assertSame('pingpong', $process->getOutput());
    }

    public function testInputStreamOnEmpty()
    {
        $i = 0;
        $input = new InputStream();
        $input->onEmpty(function () use (&$i) { ++$i; });

        $process = $this->getProcessForCode('echo 123; echo fread(STDIN, 1); echo 456;');
        $process->setInput($input);
        $process->start(function ($type, $data) use ($input) {
            if ('123' === $data) {
                $input->close();
            }
        });
        $process->wait();

        $this->assertSame(0, $i, 'InputStream->onEmpty callback should be called only when the input *becomes* empty');
        $this->assertSame('123456', $process->getOutput());
    }

    public function testIteratorOutput()
    {
        $input = new InputStream();

        $process = $this->getProcessForCode('fwrite(STDOUT, 123); fwrite(STDERR, 234); flush(); usleep(10000); fwrite(STDOUT, fread(STDIN, 3)); fwrite(STDERR, 456);');
        $process->setInput($input);
        $process->start();
        $output = [];

        foreach ($process as $type => $data) {
            $output[] = [$type, $data];
            break;
        }
        $expectedOutput = [
            [$process::OUT, '123'],
        ];
        $this->assertSame($expectedOutput, $output);

        $input->write(345);

        foreach ($process as $type => $data) {
            $output[] = [$type, $data];
        }

        $this->assertSame('', $process->getOutput());
        $this->assertFalse($process->isRunning());

        $expectedOutput = [
            [$process::OUT, '123'],
            [$process::ERR, '234'],
            [$process::OUT, '345'],
            [$process::ERR, '456'],
        ];
        $this->assertSame($expectedOutput, $output);
    }

    public function testNonBlockingNorClearingIteratorOutput()
    {
        $input = new InputStream();

        $process = $this->getProcessForCode('fwrite(STDOUT, fread(STDIN, 3));');
        $process->setInput($input);
        $process->start();
        $output = [];

        foreach ($process->getIterator($process::ITER_NON_BLOCKING | $process::ITER_KEEP_OUTPUT) as $type => $data) {
            $output[] = [$type, $data];
            break;
        }
        $expectedOutput = [
            [$process::OUT, ''],
        ];
        $this->assertSame($expectedOutput, $output);

        $input->write(123);

        foreach ($process->getIterator($process::ITER_NON_BLOCKING | $process::ITER_KEEP_OUTPUT) as $type => $data) {
            if ('' !== $data) {
                $output[] = [$type, $data];
            }
        }

        $this->assertSame('123', $process->getOutput());
        $this->assertFalse($process->isRunning());

        $expectedOutput = [
            [$process::OUT, ''],
            [$process::OUT, '123'],
        ];
        $this->assertSame($expectedOutput, $output);
    }

    public function testChainedProcesses()
    {
        $p1 = $this->getProcessForCode('fwrite(STDERR, 123); fwrite(STDOUT, 456);');
        $p2 = $this->getProcessForCode('stream_copy_to_stream(STDIN, STDOUT);');
        $p2->setInput($p1);

        $p1->start();
        $p2->run();

        $this->assertSame('123', $p1->getErrorOutput());
        $this->assertSame('', $p1->getOutput());
        $this->assertSame('', $p2->getErrorOutput());
        $this->assertSame('456', $p2->getOutput());
    }

    public function testSetBadEnv()
    {
        $process = $this->getProcess('echo hello');
        $process->setEnv(['bad%%' => '123']);
        $process->inheritEnvironmentVariables(true);

        $process->run();

        $this->assertSame('hello'.PHP_EOL, $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    public function testEnvBackupDoesNotDeleteExistingVars()
    {
        putenv('existing_var=foo');
        $_ENV['existing_var'] = 'foo';
        $process = $this->getProcess('php -r "echo getenv(\'new_test_var\');"');
        $process->setEnv(['existing_var' => 'bar', 'new_test_var' => 'foo']);
        $process->inheritEnvironmentVariables();

        $process->run();

        $this->assertSame('foo', $process->getOutput());
        $this->assertSame('foo', getenv('existing_var'));
        $this->assertFalse(getenv('new_test_var'));

        putenv('existing_var');
        unset($_ENV['existing_var']);
    }

    public function testEnvIsInherited()
    {
        $process = $this->getProcessForCode('echo serialize($_SERVER);', null, ['BAR' => 'BAZ', 'EMPTY' => '']);

        putenv('FOO=BAR');
        $_ENV['FOO'] = 'BAR';

        $process->run();

        $expected = ['BAR' => 'BAZ', 'EMPTY' => '', 'FOO' => 'BAR'];
        $env = array_intersect_key(unserialize($process->getOutput()), $expected);

        $this->assertEquals($expected, $env);

        putenv('FOO');
        unset($_ENV['FOO']);
    }

    public function testGetCommandLine()
    {
        $p = new Process(['/usr/bin/php']);

        $expected = '\\' === \DIRECTORY_SEPARATOR ? '"/usr/bin/php"' : "'/usr/bin/php'";
        $this->assertSame($expected, $p->getCommandLine());
    }

    /**
     * @dataProvider provideEscapeArgument
     */
    public function testEscapeArgument($arg)
    {
        $p = new Process([self::$phpBin, '-r', 'echo $argv[1];', $arg]);
        $p->run();

        $this->assertSame((string) $arg, $p->getOutput());
    }

    public function testRawCommandLine()
    {
        $p = Process::fromShellCommandline(sprintf('"%s" -r %s "a" "" "b"', self::$phpBin, escapeshellarg('print_r($argv);')));
        $p->run();

        $expected = <<<EOTXT
Array
(
    [0] => -
    [1] => a
    [2] => 
    [3] => b
)

EOTXT;
        $this->assertSame($expected, str_replace('Standard input code', '-', $p->getOutput()));
    }

    public function provideEscapeArgument()
    {
        yield ['a"b%c%'];
        yield ['a"b^c^'];
        yield ["a\nb'c"];
        yield ['a^b c!'];
        yield ["a!b\tc"];
        yield ['a\\\\"\\"'];
        yield ['éÉèÈàÀöä'];
        yield [null];
        yield [1];
        yield [1.1];
    }

    public function testEnvArgument()
    {
        $env = ['FOO' => 'Foo', 'BAR' => 'Bar'];
        $cmd = '\\' === \DIRECTORY_SEPARATOR ? 'echo !FOO! !BAR! !BAZ!' : 'echo $FOO $BAR $BAZ';
        $p = Process::fromShellCommandline($cmd, null, $env);
        $p->run(null, ['BAR' => 'baR', 'BAZ' => 'baZ']);

        $this->assertSame('Foo baR baZ', rtrim($p->getOutput()));
        $this->assertSame($env, $p->getEnv());
    }

    public function testWaitStoppedDeadProcess()
    {
        $process = $this->getProcess(self::$phpBin.' '.__DIR__.'/ErrorProcessInitiator.php -e '.self::$phpBin);
        $process->start();
        $process->setTimeout(2);
        $process->wait();
        $this->assertFalse($process->isRunning());
    }

    /**
     * @param string      $commandline
     * @param string|null $input
     * @param int         $timeout
     */
    private function getProcess($commandline, string $cwd = null, array $env = null, $input = null, ?int $timeout = 60): Process
    {
        if (\is_string($commandline)) {
            $process = Process::fromShellCommandline($commandline, $cwd, $env, $input, $timeout);
        } else {
            $process = new Process($commandline, $cwd, $env, $input, $timeout);
        }
        $process->inheritEnvironmentVariables();

        if (self::$process) {
            self::$process->stop(0);
        }

        return self::$process = $process;
    }

    private function getProcessForCode(string $code, string $cwd = null, array $env = null, $input = null, ?int $timeout = 60): Process
    {
        return $this->getProcess([self::$phpBin, '-r', $code], $cwd, $env, $input, $timeout);
    }
}

class NonStringifiable
{
}
