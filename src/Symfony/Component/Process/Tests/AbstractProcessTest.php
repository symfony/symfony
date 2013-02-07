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

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
abstract class AbstractProcessTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNegativeTimeoutFromConstructor()
    {
        $this->getProcess('', null, null, null, -1);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testNegativeTimeoutFromSetter()
    {
        $p = $this->getProcess('');
        $p->setTimeout(-1);
    }

    public function testNullTimeout()
    {
        $p = $this->getProcess('');
        $p->setTimeout(10);
        $p->setTimeout(null);

        $this->assertNull($p->getTimeout());
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
    public function testProcessPipes($expected, $code)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Test hangs on Windows & PHP due to https://bugs.php.net/bug.php?id=60120 and https://bugs.php.net/bug.php?id=51800');
        }

        $p = $this->getProcess(sprintf('php -r %s', escapeshellarg($code)));
        $p->setStdin($expected);
        $p->run();

        $this->assertSame($expected, $p->getOutput());
        $this->assertSame($expected, $p->getErrorOutput());
    }

    public function chainedCommandsOutputProvider()
    {
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
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Does it work on windows ?');
        }

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

    public function testGetExitCode()
    {
        $process = $this->getProcess('php -m');
        $process->run();
        $this->assertEquals(0, $process->getExitCode());
    }

    public function testIsRunning()
    {
        $process = $this->getProcess('php -r "sleep(1);"');
        $this->assertFalse($process->isRunning());
        $process->start();
        $this->assertTrue($process->isRunning());
        $process->wait();
        $this->assertFalse($process->isRunning());
    }

    public function testStop()
    {
        $process = $this->getProcess('php -r "while (true) {}"');
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

    public function testIsNotSuccessful()
    {
        $process = $this->getProcess('php -r "while (true) {}"');
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

        $process = $this->getProcess('php -r "while (true) {}"');
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

        $process = $this->getProcess('php -r "while (true) {}"');
        $process->start();
        $process->stop();

        $this->assertEquals($termSignal, $process->getTermSignal());
    }

    public function testPhpDeadlock()
    {
        $this->markTestSkipped('Can course php to hang');

        // Sleep doesn't work as it will allow the process to handle signals and close
        // file handles from the other end.
        $process = $this->getProcess('php -r "while (true) {}"');
        $process->start();

        // PHP will deadlock when it tries to cleanup $process
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
            'include \'' . __DIR__ . '/ProcessTestHelper.php\';',
        );
        $baseData = str_repeat('*', 1024);

        $codes = array();
        foreach (array(1, 16, 64, 1024, 4096) as $size) {
            $data = str_repeat($baseData, $size) . '!';
            foreach ($variations as $code) {
                $codes[] = array($data, $code);
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
