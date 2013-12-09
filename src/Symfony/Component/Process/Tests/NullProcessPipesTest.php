<?php

namespace Symfony\Component\Process\Tests;

use Symfony\Component\Process\NullProcessPipes;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessPipes;

class NullProcessPipesTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessCompletesWithNullPipes()
    {
        // Without null pipes the pipe would fill and the process will never complete
        $process = new Process('php ' . __DIR__ . '/HeavyOutputtingProcess.php');

        $process->setProcessPipes(new NullProcessPipes());
        $process->start();

        while ($process->isRunning()) {
            usleep(100e3);
        }

        // No output!
        $this->assertEquals('', $process->getOutput());
    }

    public function testReassigningPipesAfterStartIsNotAllowed()
    {
        $process = new Process('php -r "sleep(1);');

        $process->setProcessPipes(new NullProcessPipes());
        $process->start();

        $this->setExpectedException('Symfony\Component\Process\Exception\LogicException');
        $process->setProcessPipes(new ProcessPipes());
    }

    public function testRestartedProcesses() {
        $process  = new Process('echo asdf');

        $process->run();
        $this->assertEquals("asdf\n", $process->getOutput());

        $process->run();
        $this->assertEquals("asdf\n", $process->getOutput());
    }
}
