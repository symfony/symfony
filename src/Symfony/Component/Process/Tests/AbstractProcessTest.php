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

use Symfony\Component\Process\ProcessableInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * @author Robert Schönthal <seroscho@googlemail.com>
 */
abstract class AbstractProcessTest extends ProcessTestCase
{
    public function testGetIncrementalErrorOutput()
    {
        $p = new Process(sprintf('php -r %s', escapeshellarg('$n = 0; while ($n < 3) { usleep(50000); file_put_contents(\'php://stderr\', \'ERROR\'); $n++; }')));

        $p->start();
        while ($p->isRunning()) {
            $this->assertLessThanOrEqual(1, preg_match_all('/ERROR/', $p->getIncrementalErrorOutput(), $matches));
            usleep(20000);
        }
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

    public function testTTYCommand()
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does have /dev/tty support');
        }

        $process = $this->getProcess('echo "foo" >> /dev/null');
        $process->setTTY(true);
        $process->run();

        $this->assertSame(ProcessableInterface::STATUS_TERMINATED, $process->getStatus());
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

    public function testRunProcessWithTimeout()
    {
        $timeout = 0.5;
        $process = $this->getProcess('sleep 3');
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

    /**
     * tests results from sub processes
     *
     * @dataProvider pipesCodeProvider
     */
    public function testProcessPipes($code, $size)
    {
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Test hangs on Windows & PHP due to https://bugs.php.net/bug.php?id=60120 and https://bugs.php.net/bug.php?id=51800');
        }

        $expected = str_repeat(str_repeat('*', 1024), $size) . '!';
        $expectedLength = (1024 * $size) + 1;

        $p = $this->getProcess(sprintf('php -r %s', escapeshellarg($code)));
        $p->setStdin($expected);
        $p->run();

        $this->assertEquals($expectedLength, strlen($p->getOutput()));
        $this->assertEquals($expectedLength, strlen($p->getErrorOutput()));
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

    /**
     * @group idle-timeout
     */
    public function testIdleTimeout()
    {
        $process = $this->getProcess('sleep 3');
        $process->setTimeout(10);
        $process->setIdleTimeout(1);

        try {
            $process->run();

            $this->fail('A timeout exception was expected.');
        } catch (ProcessTimedOutException $ex) {
            $this->assertTrue($ex->isIdleTimeout());
            $this->assertFalse($ex->isGeneralTimeout());
            $this->assertEquals(1.0, $ex->getExceededTimeout());
        }
    }

    /**
     * @group idle-timeout
     */
    public function testIdleTimeoutNotExceededWhenOutputIsSent()
    {
        $process = $this->getProcess('echo "foo" && sleep 1 && echo "foo" && sleep 1 && echo "foo" && sleep 1 && echo "foo" && sleep 5');
        $process->setTimeout(5);
        $process->setIdleTimeout(3);

        try {
            $process->run();
            $this->fail('A timeout exception was expected.');
        } catch (ProcessTimedOutException $ex) {
            $this->assertTrue($ex->isGeneralTimeout());
            $this->assertFalse($ex->isIdleTimeout());
            $this->assertEquals(5.0, $ex->getExceededTimeout());
        }
    }

}
