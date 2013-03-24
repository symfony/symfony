<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use Symfony\Component\Console\DispatchableApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Event\ConsoleEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Event\ConsoleForExceptionEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

class DispatchableApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }
    }

    public function testRun()
    {
        $application = new DispatchableApplication();
        $application->setAutoExit(false);
        $application->setDispatcher($this->getDispatcher());

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo'));
        $this->assertEquals('before.foo.after.', $tester->getDisplay());
    }

    /**
     * @expectedException        \LogicException
     * @expectedExceptionMessage caught
     */
    public function testRunWithException()
    {
        $application = new DispatchableApplication();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            throw new \RuntimeException('foo');
        });

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo'));
    }

    public function testRunDispatchesAllEventsWithException()
    {
        $application = new DispatchableApplication();
        $application->setDispatcher($this->getDispatcher());
        $application->setAutoExit(false);

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');

            throw new \RuntimeException('foo');
        });

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo'));
        $this->assertContains('before.foo.after.caught.', $tester->getDisplay());
    }


    public function testCommandListenerCanChangeCommand()
    {
        $application = new DispatchableApplication();
        $application->setDispatcher($dispatcher = $this->getDispatcher());
        $application->setAutoExit(false);

        $dispatcher->addListener('console.command', function (ConsoleEvent $event) {
            $event->setCommand($event->getCommand()->getApplication()->find('help'));
        });

        $application->register('foo')->setCode(function (InputInterface $input, OutputInterface $output) {
            $output->write('foo.');
        });

        $tester = new ApplicationTester($application);
        $tester->run(array('command' => 'foo'));
        $this->assertNotContains('foo.', $tester->getDisplay());
        $this->assertContains('The help command displays', $tester->getDisplay());
    }

    protected function getDispatcher()
    {
        $dispatcher = new EventDispatcher;
        $dispatcher->addListener('console.command', function (ConsoleEvent $event) {
            $event->getOutput()->write('before.');
        });
        $dispatcher->addListener('console.terminate', function (ConsoleTerminateEvent $event) {
            $event->getOutput()->write('after.');

            $event->setExitCode(128);
        });
        $dispatcher->addListener('console.exception', function (ConsoleForExceptionEvent $event) {
            $event->getOutput()->writeln('caught.');

            $event->setException(new \LogicException('caught.', $event->getExitCode(), $event->getException()));
        });

        return $dispatcher;
    }
}
