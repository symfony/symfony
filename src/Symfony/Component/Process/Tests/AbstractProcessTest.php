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

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
abstract class AbstractProcessTest extends \PHPUnit_Framework_TestCase
{
    public function testThatProcessDoesNotThrowWarningDuringRun()
    {
        @trigger_error('Test Error', E_USER_NOTICE);
        $process = $this->getProcess("php -r 'sleep(3)'");
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

    public function testStopWithTimeoutIsActuallyWorking()
    {
        $this->verifyPosixIsEnabled();

        // exec is mandatory here since we send a signal to the process
        // see https://github.com/symfony/symfony/issues/5030 about prepending
        // command with exec
        $p = $this->getProcess('exec php '.__DIR__.'/NonStopableProcess.php 3');
        $p->start();
        usleep(100000);
        $start = microtime(true);
        $p->stop(1.1, SIGKILL);
        while ($p->isRunning()) {
            usleep(1000);
        }
        $duration = microtime(true) - $start;

        $this->assertLessThan(1.8, $duration);
    }

    public function testCallbacksAreExecutedWithStart()
    {
        $data = '';

        $process = $this->getProcess('echo foo && php -r "sleep(1);" && echo foo');
        $process->start(function ($type, $buffer) use (&$data) {
            $data .= $buffer;
        });

        while ($process->isRunning()) {
            usleep(10000);
        }

        $this->assertEquals(2, preg_match_all('/foo/', $data, $matches));
    }

    /**
     * tests results from sub processes
     *
     * @dataProvider responsesCodeProvider
     */
    public function testProcessResponses($expected, $getter, $code)
    {
        $p = $this->getProcess(sprintf('php -r %s', escapeshellarg($code)));
        $p->run();

        $this->assertSame($expected, $p->$getter());
    }

    /**
     * tests results from sub processes
     *
     * @dataProvider pipesCodeProvider
     */
    public function testProcessPipes($code, $size)
    {
        $expected = str_repeat(str_repeat('*', 1024), $size) . '!';
        $expectedLength = (1024 * $size) + 1;

        $p = $this->getProcess(sprintf('php -r %s', escapeshellarg($code)));
        $p->setStdin($expected);
        $p->run();

        $this->assertEquals($expectedLength, strlen($p->getOutput()));
        $this->assertEquals($expectedLength, strlen($p->getErrorOutput()));
    }

    public function chainedCommandsOutputProvider()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            return array(
                array("2 \r\n2\r\n", '&&', '2')
            );
        }

        return array(
            array("1\n1\n", ';', '1'),
            array("2\n2\n", '&&', '2'),
        );
    }

    /**
     *
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
        $p = $this->getProcess(sprintf('php -r %s', escapeshellarg('echo \'foo\';')));

        $called = false;
        $p->run(function ($type, $buffer) use (&$called) {
            $called = $buffer === 'foo';
        });

        $this->assertTrue($called, 'The callback should be executed with the output');
    }

    public function testGetErrorOutput()
    {
        $p = new Process(sprintf('php -r %s', escapeshellarg('$n = 0; while ($n < 3) { file_put_contents(\'php://stderr\', \'ERROR\'); $n++; }')));

        $p->run();
        $this->assertEquals(3, preg_match_all('/ERROR/', $p->getErrorOutput(), $matches));
    }

    public function testGetIncrementalErrorOutput()
    {
        $p = new Process(sprintf('php -r %s', escapeshellarg('$n = 0; while ($n < 3) { usleep(50000); file_put_contents(\'php://stderr\', \'ERROR\'); $n++; }')));

        $p->start();
        while ($p->isRunning()) {
            $this->assertLessThanOrEqual(1, preg_match_all('/ERROR/', $p->getIncrementalErrorOutput(), $matches));
            usleep(20000);
        }
    }

    public function testGetOutput()
    {
        $p = new Process(sprintf('php -r %s', escapeshellarg('$n=0;while ($n<3) {echo \' foo \';$n++; usleep(500); }')));

        $p->run();
        $this->assertEquals(3, preg_match_all('/foo/', $p->getOutput(), $matches));
    }

    public function testGetIncrementalOutput()
    {
        $p = new Process(sprintf('php -r %s', escapeshellarg('$n=0;while ($n<3) { echo \' foo \'; usleep(50000); $n++; }')));

        $p->start();
        while ($p->isRunning()) {
            $this->assertLessThanOrEqual(1, preg_match_all('/foo/', $p->getIncrementalOutput(), $matches));
            usleep(20000);
        }
    }

    public function testExitCodeCommandFailed()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support POSIX exit code');
        }

        // such command run in bash return an exitcode 127
        $process = $this->getProcess('nonexistingcommandIhopeneversomeonewouldnameacommandlikethis');
        $process->run();

        $this->assertGreaterThan(0, $process->getExitCode());
    }

    public function testTTYCommand()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does have /dev/tty support');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTTY(true);
        $process->run();

        $this->assertSame(Process::STATUS_TERMINATED, $process->getStatus());
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
        $process = $this->getProcess('php -r "sleep(4);"');
        $start = microtime(true);
        $process->start();
        $end = microtime(true);
        $this->assertLessThan(1 , $end-$start);
    }

    public function testUpdateStatus()
    {
        $process = $this->getProcess('php -h');
        $process->run();
        $this->assertTrue(strlen($process->getOutput()) > 0);
    }

    public function testGetExitCodeIsNullOnStart()
    {
        $process = $this->getProcess('php -r "usleep(200000);"');
        $this->assertNull($process->getExitCode());
        $process->start();
        $this->assertNull($process->getExitCode());
        $process->wait();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetExitCodeIsNullOnWhenStartingAgain()
    {
        $process = $this->getProcess('php -r "usleep(200000);"');
        $process->run();
        $this->assertEquals(0, $process->getExitCode());
        $process->start();
        $this->assertNull($process->getExitCode());
        $process->wait();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testGetExitCode()
    {
        $process = $this->getProcess('php -m');
        $process->run();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testStatus()
    {
        $process = $this->getProcess('php -r "usleep(500000);"');
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
        $process = $this->getProcess('php -r "sleep(4);"');
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop();
        $this->assertFalse($process->isRunning());
    }

    public function testIsSuccessful()
    {
        $process = $this->getProcess('php -m');
        $process->run();
        $this->assertTrue($process->isSuccessful());
    }

    public function testIsSuccessfulOnlyAfterTerminated()
    {
        $process = $this->getProcess('php -r "sleep(1);"');
        $process->start();
        while ($process->isRunning()) {
            $this->assertFalse($process->isSuccessful());
            usleep(300000);
        }

        $this->assertTrue($process->isSuccessful());
    }

    public function testIsNotSuccessful()
    {
        $process = $this->getProcess('php -r "sleep(4);"');
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->stop();
        $this->assertFalse($process->isSuccessful());
    }

    public function testProcessIsNotSignaled()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $process = $this->getProcess('php -m');
        $process->run();
        $this->assertFalse($process->hasBeenSignaled());
    }

    public function testProcessWithoutTermSignalIsNotSignaled()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $process = $this->getProcess('php -m');
        $process->run();
        $this->assertFalse($process->hasBeenSignaled());
    }

    public function testProcessWithoutTermSignal()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $process = $this->getProcess('php -m');
        $process->run();
        $this->assertEquals(0, $process->getTermSignal());
    }

    public function testProcessIsSignaledIfStopped()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        $process = $this->getProcess('php -r "sleep(4);"');
        $process->start();
        $process->stop();
        $this->assertTrue($process->hasBeenSignaled());
    }

    public function testProcessWithTermSignal()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        // SIGTERM is only defined if pcntl extension is present
        $termSignal = defined('SIGTERM') ? SIGTERM : 15;

        $process = $this->getProcess('php -r "sleep(4);"');
        $process->start();
        $process->stop();

        $this->assertEquals($termSignal, $process->getTermSignal());
    }

    public function testProcessThrowsExceptionWhenExternallySignaled()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support POSIX signals');
        }

        if (!function_exists('posix_kill')) {
            $this->markTestSkipped('posix_kill is required for this test');
        }

        $termSignal = defined('SIGKILL') ? SIGKILL : 9;

        $process = $this->getProcess('exec php -r "while (true) {}"');
        $process->start();
        posix_kill($process->getPid(), $termSignal);

        $this->setExpectedException('Symfony\Component\Process\Exception\RuntimeException', 'The process has been signaled with signal "9".');
        $process->wait();
    }

    public function testRestart()
    {
        $process1 = $this->getProcess('php -r "echo getmypid();"');
        $process1->run();
        $process2 = $process1->restart();

        usleep(300000); // wait for output

        // Ensure that both processed finished and the output is numeric
        $this->assertFalse($process1->isRunning());
        $this->assertFalse($process2->isRunning());
        $this->assertTrue(is_numeric($process1->getOutput()));
        $this->assertTrue(is_numeric($process2->getOutput()));

        // Ensure that restart returned a new process by check that the output is different
        $this->assertNotEquals($process1->getOutput(), $process2->getOutput());
    }

    public function testPhpDeadlock()
    {
        $this->markTestSkipped('Can course PHP to hang');

        // Sleep doesn't work as it will allow the process to handle signals and close
        // file handles from the other end.
        $process = $this->getProcess('php -r "while (true) {}"');
        $process->start();

        // PHP will deadlock when it tries to cleanup $process
    }

    public function testRunProcessWithTimeout()
    {
        $timeout = 0.5;
        $process = $this->getProcess('php -r "sleep(3);"');
        $process->setTimeout($timeout);
        $start = microtime(true);
        try {
            $process->run();
            $this->fail('A RuntimeException should have been raised');
        } catch (RuntimeException $e) {

        }
        $duration = microtime(true) - $start;

        $this->assertLessThan($timeout + Process::TIMEOUT_PRECISION, $duration);
    }

    public function testCheckTimeoutOnStartedProcess()
    {
        $timeout = 0.5;
        $precision = 100000;
        $process = $this->getProcess('php -r "sleep(3);"');
        $process->setTimeout($timeout);
        $start = microtime(true);

        $process->start();

        try {
            while ($process->isRunning()) {
                $process->checkTimeout();
                usleep($precision);
            }
            $this->fail('A RuntimeException should have been raised');
        } catch (RuntimeException $e) {

        }
        $duration = microtime(true) - $start;

        $this->assertLessThan($timeout + $precision, $duration);
        $this->assertFalse($process->isSuccessful());
    }

    public function testStartAfterATimeout()
    {
        $process = $this->getProcess('php -r "while (true) {echo \'\'; usleep(1000); }"');
        $process->setTimeout(0.1);
        try {
            $process->run();
            $this->fail('An exception should have been raised.');
        } catch (\Exception $e) {

        }
        $process->start();
        usleep(10000);
        $process->stop();
    }

    public function testGetPid()
    {
        $process = $this->getProcess('php -r "sleep(1);"');
        $process->start();
        $this->assertGreaterThan(0, $process->getPid());
        $process->stop();
    }

    public function testGetPidIsNullBeforeStart()
    {
        $process = $this->getProcess('php -r "sleep(1);"');
        $this->assertNull($process->getPid());
    }

    public function testGetPidIsNullAfterRun()
    {
        $process = $this->getProcess('php -m');
        $process->run();
        $this->assertNull($process->getPid());
    }

    public function testSignal()
    {
        $this->verifyPosixIsEnabled();

        $process = $this->getProcess('exec php -f ' . __DIR__ . '/SignalListener.php');
        $process->start();
        usleep(500000);
        $process->signal(SIGUSR1);

        while ($process->isRunning() && false === strpos($process->getoutput(), 'Caught SIGUSR1')) {
            usleep(10000);
        }

        $this->assertEquals('Caught SIGUSR1', $process->getOutput());
    }

    public function testExitCodeIsAvailableAfterSignal()
    {
        $this->verifyPosixIsEnabled();

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
     */
    public function testSignalProcessNotRunning()
    {
        $this->verifyPosixIsEnabled();
        $process = $this->getProcess('php -m');
        $process->signal(SIGHUP);
    }

    private function verifyPosixIsEnabled()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('POSIX signals do not work on Windows');
        }
        if (!defined('SIGUSR1')) {
            $this->markTestSkipped('The pcntl extension is not enabled');
        }
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testSignalWithWrongIntSignal()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('POSIX signals do not work on Windows');
        }

        $process = $this->getProcess('php -r "sleep(3);"');
        $process->start();
        $process->signal(-4);
    }

    /**
     * @expectedException \Symfony\Component\Process\Exception\RuntimeException
     */
    public function testSignalWithWrongNonIntSignal()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('POSIX signals do not work on Windows');
        }

        $process = $this->getProcess('php -r "sleep(3);"');
        $process->start();
        $process->signal('Céphalopodes');
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

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
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
     * provides default method names for simple getter/setter
     */
    public function methodProvider()
    {
        $defaults = array(
            array('CommandLine'),
            array('Timeout'),
            array('WorkingDirectory'),
            array('Env'),
            array('Stdin'),
            array('Options')
        );

        return $defaults;
    }

    /**
     * @param string  $commandline
     * @param null    $cwd
     * @param array   $env
     * @param null    $stdin
     * @param integer $timeout
     * @param array   $options
     *
     * @return Process
     */
    abstract protected function getProcess($commandline, $cwd = null, array $env = null, $stdin = null, $timeout = 60, array $options = array());
}
