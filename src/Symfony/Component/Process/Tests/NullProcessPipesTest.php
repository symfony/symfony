<?php


namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\NullProcessPipes;
use Symfony\Component\Process\Process;

class NullProcessPipesTest extends \PHPUnit_Framework_TestCase {
    public function testProcessCompletesWithNullPipes() {
        // Without null pipes the pipe would fill and the process will never complete
        $process = new Process('php ' . __DIR__ . '/HeavyOutputtingProcess.php');
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->markTestSkipped('Windows does not support NullProcessPipes');
        }
        $process->setProcessPipes(new NullProcessPipes());
        $process->start();

        while($process->isRunning()) {
            usleep(100e3);
        }

        // No output!
        $this->assertEquals('', $process->getOutput());
    }
}
