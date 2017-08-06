<?php

namespace Symfony\Component\Worker\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Worker\Command\WorkerListCommand;

class WorkerListCommandTest extends TestCase
{
    public function testExecute()
    {
        $expected = <<<EOTXT

Available workers
=================


 * foo
 * bar


EOTXT;

        $tester = new CommandTester(new WorkerListCommand(array('foo', 'bar')));
        $tester->execute(array());
        $this->assertSame($expected, $tester->getDisplay());
    }

    public function testExecuteNoWorker()
    {
        $tester = new CommandTester(new WorkerListCommand());
        $tester->execute(array(), array('decorated' => false));
        $this->assertContains('[ERROR] There are no available workers.', $tester->getDisplay());
    }
}
