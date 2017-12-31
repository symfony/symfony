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
    private static $notEnhancedSigchild = false;

    public static function setUpBeforeClass()
    {
        $phpBin = new PhpExecutableFinder();
        self::$phpBin = getenv('SYMFONY_PROCESS_PHP_TEST_BINARY') ?: ('phpdbg' === PHP_SAPI ? 'php' : $phpBin->find());

        ob_start();
        phpinfo(INFO_GENERAL);
        self::$sigchild = false !== strpos(ob_get_clean(), '--enable-sigchild');
    }

    protected function tearDown()
    {
        if (self::$process) {
            self::$process->stop(0);
            self::$process = null;
        }
    }

    /**
     * @group legacy
     * @expectedDeprecation The provided cwd does not exist. Command is currently ran against getcwd(). This behavior is deprecated since Symfony 3.4 and will be removed in 4.0.
     */
    public function testInvalidCwd()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('False-positive on Windows/appveyor.');
        }

        // Check that it works fine if the CWD exists
        $cmd = new Process('echo test', __DIR__);
        $cmd->run();

        $cmd = new Process('echo test', __DIR__.'/notfound/');
        $cmd->run();
    }

    public function testThatProcessDoesNotThrowWarningDuringRun()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is transient on Windows');
        }
        @trigger_error('Test Error', E_USER_NOTICE);
        $process = $this->getProcessForCode('sleep(3)');
        $process->run();
        $actualError = error_get_last();
        $this->assertEquals('Test Error', $actualError['message']);
        $this->assertEquals(E_USER_NOTICE, $actualError['type']);
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     */
    public function testNegativeTimeoutFromConstructor()
    {
        $this->getProcess('', null, null, null, -1);
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     */
    public function testNegativeTimeoutFromSetter()
    {
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
        $p = $this->getProcess(array(self::$phpBin, __DIR__.'/NonStopableProcess.php', 30));
        $p->start();

        while (false === strpos($p->getOutput(), 'received')) {
            usleep(1000);
        }
        $start = microtime(true);
        $p->stop(0.1);

        $p->wait();

        $this->assertLessThan(15, microtime(true) - $start);
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

        $this->assertEquals($expectedOutputSize, strlen($o));
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

        $this->assertEquals($expectedLength, strlen($p->getOutput()));
        $this->assertEquals($expectedLength, strlen($p->getErrorOutput()));
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

        $this->assertEquals($expectedLength, strlen($p->getOutput()));
        $this->assertEquals($expectedLength, strlen($p->getErrorOutput()));
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

    /**
     * @expectedException \Symfony\Component\Process\Exception\LogicException
     * @expectedExceptionMessage Input can not be set while the process is running.
     */
    public function testSetInputWhileRunningThrowsAnException()
    {
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
     * @expectedException \Symfony\Component\Process\Exception\InvalidArgumentException
     * @expectedExceptionMessage Symfony\Component\Process\Process::setInput only accepts strings, Traversable objects or stream resources.
     */
    public function testInvalidInput($value)
    {
        $process = $this->getProcess('foo');
        $process->setInput($value);
    }

    public function provideInvalidInputValues()
    {
        return array(
            array(array()),
            array(new NonStringifiable()),
        );
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
        return array(
            array(null, null),
            array('24.5', 24.5),
            array('input data', 'input data'),
        );
    }

    public function chainedCommandsOutputProvider()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            return array(
                array("2 \r\n2\r\n", '&&', '2'),
            );
        }

        return array(
            array("1\n1\n", ';', '1'),
            array("2\n2\n", '&&', '2'),
        );
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

        foreach (array('foo', 'bar') as $s) {
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
        return array(
            array('getOutput', 'getIncrementalOutput', 'php://stdout'),
            array('getErrorOutput', 'getIncrementalErrorOutput', 'php://stderr'),
        );
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
        if ('\\' === DIRECTORY_SEPARATOR) {
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
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX exit code');
        }
        $this->skipIfNotEnhancedSigchild();

        // such command run in bash return an exitcode 127
        $process = $this->getProcess('nonexistingcommandIhopeneversomeonewouldnameacommandlikethis');
        $process->run();

        $this->assertGreaterThan(0, $process->getExitCode());
    }

    /**
     * @group tty
     */
    public function testTTYCommand()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not have /dev/tty support');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null && '.$this->getProcessForCode('usleep(100000);')->getCommandLine());
        $process->setTty(true);
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->wait();

        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
    }

    /**
     * @group tty
     */
    public function testTTYCommandExitCode()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does have /dev/tty support');
        }
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTty(true);
        $process->run();

        $this->assertTrue($process->isSuccessful());
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     * @expectedExceptionMessage TTY mode is not supported on Windows platform.
     */
    public function testTTYInWindowsEnvironment()
    {
        if ('\\' !== DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('This test is for Windows platform only');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTty(false);
        $process->setTty(true);
    }

    public function testExitCodeTextIsNullWhenExitCodeIsNull()
    {
        $this->skipIfNotEnhancedSigchild();

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
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');

        $this->assertSame($process, $process->mustRun());
        $this->assertEquals('foo'.PHP_EOL, $process->getOutput());
    }

    public function testSuccessfulMustRunHasCorrectExitCode()
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo')->mustRun();
        $this->assertEquals(0, $process->getExitCode());
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function testMustRunThrowsException()
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('exit 1');
        $process->mustRun();
    }

    public function testExitCodeText()
    {
        $this->skipIfNotEnhancedSigchild();

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
        $this->assertGreaterThan(0, strlen($process->getOutput()));
    }

    public function testGetExitCodeIsNullOnStart()
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('usleep(100000);');
        $this->assertNull($process->getExitCode());
        $process->start();
        $this->assertNull($process->getExitCode());
        $process->wait();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetExitCodeIsNullOnWhenStartingAgain()
    {
        $this->skipIfNotEnhancedSigchild();

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
        $this->skipIfNotEnhancedSigchild();

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
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertTrue($process->isSuccessful());
    }

    public function testIsSuccessfulOnlyAfterTerminated()
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('usleep(100000);');
        $process->start();

        $this->assertFalse($process->isSuccessful());

        $process->wait();

        $this->assertTrue($process->isSuccessful());
    }

    public function testIsNotSuccessful()
    {
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('throw new \Exception(\'BOUM\');');
        $process->run();
        $this->assertFalse($process->isSuccessful());
    }

    public function testProcessIsNotSignaled()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertFalse($process->hasBeenSignaled());
    }

    public function testProcessWithoutTermSignal()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcess('echo foo');
        $process->run();
        $this->assertEquals(0, $process->getTermSignal());
    }

    public function testProcessIsSignaledIfStopped()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }
        $this->skipIfNotEnhancedSigchild();

        $process = $this->getProcessForCode('sleep(32);');
        $process->start();
        $process->stop();
        $this->assertTrue($process->hasBeenSignaled());
        $this->assertEquals(15, $process->getTermSignal()); // SIGTERM
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     * @expectedExceptionMessage The process has been signaled
     */
    public function testProcessThrowsExceptionWhenExternallySignaled()
    {
        if (!function_exists('posix_kill')) {
            $this->markTestSkipped('Function posix_kill is required.');
        }
        $this->skipIfNotEnhancedSigchild(false);

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
        $this->assertInternalType('numeric', $process1->getOutput());
        $this->assertInternalType('numeric', $process2->getOutput());

        // Ensure that restart returned a new process by check that the output is different
        $this->assertNotEquals($process1->getOutput(), $process2->getOutput());
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\ProcessTimedOutException
     * @expectedExceptionMessage exceeded the timeout of 0.1 seconds.
     */
    public function testRunProcessWithTimeout()
    {
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

    /**
     * @expectedException \Symfony\Component\Process\Exception\ProcessTimedOutException
     * @expectedExceptionMessage exceeded the timeout of 0.1 seconds.
     */
    public function testIterateOverProcessWithTimeout()
    {
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

    /**
     * @expectedException \Symfony\Component\Process\Exception\ProcessTimedOutException
     * @expectedExceptionMessage exceeded the timeout of 0.1 seconds.
     */
    public function testCheckTimeoutOnStartedProcess()
    {
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

    /**
     * @expectedException \Symfony\Component\Process\Exception\ProcessTimedOutException
     * @expectedExceptionMessage exceeded the timeout of 0.1 seconds.
     */
    public function testStartAfterATimeout()
    {
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
        $process = $this->getProcess(array(self::$phpBin, __DIR__.'/SignalListener.php'));
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
        $this->skipIfNotEnhancedSigchild();

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

    /**
     * @expectedException \Symfony\Component\Process\Exception\LogicException
     * @expectedExceptionMessage Can not send signal on a non running process.
     */
    public function testSignalProcessNotRunning()
    {
        $process = $this->getProcess('foo');
        $process->signal(1); // SIGHUP
    }

    /**
     * @dataProvider provideMethodsThatNeedARunningProcess
     */
    public function testMethodsThatNeedARunningProcess($method)
    {
        $process = $this->getProcess('foo');

        if (method_exists($this, 'expectException')) {
            $this->expectException('Symfony\Component\Process\Exception\LogicException');
            $this->expectExceptionMessage(sprintf('Process must be started before calling %s.', $method));
        } else {
            $this->setExpectedException('Symfony\Component\Process\Exception\LogicException', sprintf('Process must be started before calling %s.', $method));
        }

        $process->{$method}();
    }

    public function provideMethodsThatNeedARunningProcess()
    {
        return array(
            array('getOutput'),
            array('getIncrementalOutput'),
            array('getErrorOutput'),
            array('getIncrementalErrorOutput'),
            array('wait'),
        );
    }

    /**
     * @dataProvider provideMethodsThatNeedATerminatedProcess
     * @expectedException \Symfony\Component\Process\Exception\LogicException
     * @expectedExceptionMessage Process must be terminated before calling
     */
    public function testMethodsThatNeedATerminatedProcess($method)
    {
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
        return array(
            array('hasBeenSignaled'),
            array('getTermSignal'),
            array('hasBeenStopped'),
            array('getStopSignal'),
        );
    }

    /**
     * @dataProvider provideWrongSignal
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testWrongSignal($signal)
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('POSIX signals do not work on Windows');
        }

        $process = $this->getProcessForCode('sleep(38);');
        $process->start();
        try {
            $process->signal($signal);
            $this->fail('A RuntimeException must have been thrown');
        } catch (RuntimeException $e) {
            $process->stop(0);
        }

        throw $e;
    }

    public function provideWrongSignal()
    {
        return array(
            array(-4),
            array('Céphalopodes'),
        );
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

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     * @expectedExceptionMessage Disabling output while the process is running is not possible.
     */
    public function testDisableOutputWhileRunningThrowsException()
    {
        $p = $this->getProcessForCode('sleep(39);');
        $p->start();
        $p->disableOutput();
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     * @expectedExceptionMessage Enabling output while the process is running is not possible.
     */
    public function testEnableOutputWhileRunningThrowsException()
    {
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

    /**
     * @expectedException \Symfony\Component\Process\Exception\LogicException
     * @expectedExceptionMessage Output can not be disabled while an idle timeout is set.
     */
    public function testDisableOutputWhileIdleTimeoutIsSet()
    {
        $process = $this->getProcess('foo');
        $process->setIdleTimeout(1);
        $process->disableOutput();
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\LogicException
     * @expectedExceptionMessage timeout can not be set while the output is disabled.
     */
    public function testSetIdleTimeoutWhileOutputIsDisabled()
    {
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
     * @expectedException \Symfony\Component\Process\Exception\LogicException
     * @expectedExceptionMessage Output has been disabled.
     */
    public function testGetOutputWhileDisabled($fetchMethod)
    {
        $p = $this->getProcessForCode('sleep(41);');
        $p->disableOutput();
        $p->start();
        $p->{$fetchMethod}();
    }

    public function provideOutputFetchingMethods()
    {
        return array(
            array('getOutput'),
            array('getIncrementalOutput'),
            array('getErrorOutput'),
            array('getIncrementalErrorOutput'),
        );
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
        return array(
            //expected output / getter / code to execute
            //array(1,'getExitCode','exit(1);'),
            //array(true,'isSuccessful','exit();'),
            array('output', 'getOutput', 'echo \'output\';'),
        );
    }

    public function pipesCodeProvider()
    {
        $variations = array(
            'fwrite(STDOUT, $in = file_get_contents(\'php://stdin\')); fwrite(STDERR, $in);',
            'include \''.__DIR__.'/PipeStdinInStdoutStdErrStreamSelect.php\';',
        );

        if ('\\' === DIRECTORY_SEPARATOR) {
            // Avoid XL buffers on Windows because of https://bugs.php.net/bug.php?id=65650
            $sizes = array(1, 2, 4, 8);
        } else {
            $sizes = array(1, 16, 64, 1024, 4096);
        }

        $codes = array();
        foreach ($sizes as $size) {
            foreach ($variations as $code) {
                $codes[] = array($code, $size);
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
        return array(
            array('php://stdout', 'getIncrementalOutput'),
            array('php://stderr', 'getIncrementalErrorOutput'),
        );
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
        $output = array();

        foreach ($process as $type => $data) {
            $output[] = array($type, $data);
            break;
        }
        $expectedOutput = array(
            array($process::OUT, '123'),
        );
        $this->assertSame($expectedOutput, $output);

        $input->write(345);

        foreach ($process as $type => $data) {
            $output[] = array($type, $data);
        }

        $this->assertSame('', $process->getOutput());
        $this->assertFalse($process->isRunning());

        $expectedOutput = array(
            array($process::OUT, '123'),
            array($process::ERR, '234'),
            array($process::OUT, '345'),
            array($process::ERR, '456'),
        );
        $this->assertSame($expectedOutput, $output);
    }

    public function testNonBlockingNorClearingIteratorOutput()
    {
        $input = new InputStream();

        $process = $this->getProcessForCode('fwrite(STDOUT, fread(STDIN, 3));');
        $process->setInput($input);
        $process->start();
        $output = array();

        foreach ($process->getIterator($process::ITER_NON_BLOCKING | $process::ITER_KEEP_OUTPUT) as $type => $data) {
            $output[] = array($type, $data);
            break;
        }
        $expectedOutput = array(
            array($process::OUT, ''),
        );
        $this->assertSame($expectedOutput, $output);

        $input->write(123);

        foreach ($process->getIterator($process::ITER_NON_BLOCKING | $process::ITER_KEEP_OUTPUT) as $type => $data) {
            if ('' !== $data) {
                $output[] = array($type, $data);
            }
        }

        $this->assertSame('123', $process->getOutput());
        $this->assertFalse($process->isRunning());

        $expectedOutput = array(
            array($process::OUT, ''),
            array($process::OUT, '123'),
        );
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
        $process->setEnv(array('bad%%' => '123'));
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
        $process->setEnv(array('existing_var' => 'bar', 'new_test_var' => 'foo'));
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
        $process = $this->getProcessForCode('echo serialize($_SERVER);', null, array('BAR' => 'BAZ', 'EMPTY' => ''));

        putenv('FOO=BAR');
        $_ENV['FOO'] = 'BAR';

        $process->run();

        $expected = array('BAR' => 'BAZ', 'EMPTY' => '', 'FOO' => 'BAR');
        $env = array_intersect_key(unserialize($process->getOutput()), $expected);

        $this->assertEquals($expected, $env);

        putenv('FOO');
        unset($_ENV['FOO']);
    }

    /**
     * @group legacy
     */
    public function testInheritEnvDisabled()
    {
        $process = $this->getProcessForCode('echo serialize($_SERVER);', null, array('BAR' => 'BAZ'));

        putenv('FOO=BAR');
        $_ENV['FOO'] = 'BAR';

        $this->assertSame($process, $process->inheritEnvironmentVariables(false));
        $this->assertFalse($process->areEnvironmentVariablesInherited());

        $process->run();

        $expected = array('BAR' => 'BAZ', 'FOO' => 'BAR');
        $env = array_intersect_key(unserialize($process->getOutput()), $expected);
        unset($expected['FOO']);

        $this->assertSame($expected, $env);

        putenv('FOO');
        unset($_ENV['FOO']);
    }

    public function testGetCommandLine()
    {
        $p = new Process(array('/usr/bin/php'));

        $expected = '\\' === DIRECTORY_SEPARATOR ? '"/usr/bin/php"' : "'/usr/bin/php'";
        $this->assertSame($expected, $p->getCommandLine());
    }

    /**
     * @dataProvider provideEscapeArgument
     */
    public function testEscapeArgument($arg)
    {
        $p = new Process(array(self::$phpBin, '-r', 'echo $argv[1];', $arg));
        $p->run();

        $this->assertSame($arg, $p->getOutput());
    }

    /**
     * @dataProvider provideEscapeArgument
     * @group legacy
     */
    public function testEscapeArgumentWhenInheritEnvDisabled($arg)
    {
        $p = new Process(array(self::$phpBin, '-r', 'echo $argv[1];', $arg), null, array('BAR' => 'BAZ'));
        $p->inheritEnvironmentVariables(false);
        $p->run();

        $this->assertSame($arg, $p->getOutput());
    }

    public function testRawCommandLine()
    {
        $p = new Process(sprintf('"%s" -r %s "a" "" "b"', self::$phpBin, escapeshellarg('print_r($argv);')));
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
        yield array('a"b%c%');
        yield array('a"b^c^');
        yield array("a\nb'c");
        yield array('a^b c!');
        yield array("a!b\tc");
        yield array('a\\\\"\\"');
        yield array('éÉèÈàÀöä');
    }

    public function testEnvArgument()
    {
        $env = array('FOO' => 'Foo', 'BAR' => 'Bar');
        $cmd = '\\' === DIRECTORY_SEPARATOR ? 'echo !FOO! !BAR! !BAZ!' : 'echo $FOO $BAR $BAZ';
        $p = new Process($cmd, null, $env);
        $p->run(null, array('BAR' => 'baR', 'BAZ' => 'baZ'));

        $this->assertSame('Foo baR baZ', rtrim($p->getOutput()));
        $this->assertSame($env, $p->getEnv());
    }

    /**
     * @param string      $commandline
     * @param null|string $cwd
     * @param null|array  $env
     * @param null|string $input
     * @param int         $timeout
     * @param array       $options
     *
     * @return Process
     */
    private function getProcess($commandline, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        $process = new Process($commandline, $cwd, $env, $input, $timeout);
        $process->inheritEnvironmentVariables();

        if (false !== $enhance = getenv('ENHANCE_SIGCHLD')) {
            try {
                $process->setEnhanceSigchildCompatibility(false);
                $process->getExitCode();
                $this->fail('ENHANCE_SIGCHLD must be used together with a sigchild-enabled PHP.');
            } catch (RuntimeException $e) {
                $this->assertSame('This PHP has been compiled with --enable-sigchild. You must use setEnhanceSigchildCompatibility() to use this method.', $e->getMessage());
                if ($enhance) {
                    $process->setEnhanceSigchildCompatibility(true);
                } else {
                    self::$notEnhancedSigchild = true;
                }
            }
        }

        if (self::$process) {
            self::$process->stop(0);
        }

        return self::$process = $process;
    }

    /**
     * @return Process
     */
    private function getProcessForCode($code, $cwd = null, array $env = null, $input = null, $timeout = 60)
    {
        return $this->getProcess(array(self::$phpBin, '-r', $code), $cwd, $env, $input, $timeout);
    }

    private function skipIfNotEnhancedSigchild($expectException = true)
    {
        if (self::$sigchild) {
            if (!$expectException) {
                $this->markTestSkipped('PHP is compiled with --enable-sigchild.');
            } elseif (self::$notEnhancedSigchild) {
                if (method_exists($this, 'expectException')) {
                    $this->expectException('Symfony\Component\Process\Exception\RuntimeException');
                    $this->expectExceptionMessage('This PHP has been compiled with --enable-sigchild.');
                } else {
                    $this->setExpectedException('Symfony\Component\Process\Exception\RuntimeException', 'This PHP has been compiled with --enable-sigchild.');
                }
            }
        }
    }
}

class NonStringifiable
{
}
