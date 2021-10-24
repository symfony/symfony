<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Command\EventDispatcherDebugCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\Event;

class EventDispatcherDebugCommandTest extends TestCase
{
    private $fs;

    // public function testDumpMessagesAndClean()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
    //     $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
    //     $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    // }

    // public function testDumpMessagesAsTreeAndClean()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--as-tree' => 1]);
    //     $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
    //     $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    // }

    // public function testDumpSortedMessagesAndClean()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'asc']);
    //     $this->assertMatchesRegularExpression("/\*bar\*foo\*test/", preg_replace('/\s+/', '', $tester->getDisplay()));
    //     $this->assertMatchesRegularExpression('/3 messages were successfully extracted/', $tester->getDisplay());
    // }

    // public function testDumpReverseSortedMessagesAndClean()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'desc']);
    //     $this->assertMatchesRegularExpression("/\*test\*foo\*bar/", preg_replace('/\s+/', '', $tester->getDisplay()));
    //     $this->assertMatchesRegularExpression('/3 messages were successfully extracted/', $tester->getDisplay());
    // }

    // public function testDumpSortWithoutValueAndClean()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort']);
    //     $this->assertMatchesRegularExpression("/\*bar\*foo\*test/", preg_replace('/\s+/', '', $tester->getDisplay()));
    //     $this->assertMatchesRegularExpression('/3 messages were successfully extracted/', $tester->getDisplay());
    // }

    // public function testDumpWrongSortAndClean()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'test']);
    //     $this->assertMatchesRegularExpression('/\[ERROR\] Wrong sort order/', $tester->getDisplay());
    // }

    // public function testDumpMessagesAndCleanInRootDirectory()
    // {
    //     $this->fs->remove($this->translationDir);
    //     $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
    //     $this->fs->mkdir($this->translationDir.'/translations');
    //     $this->fs->mkdir($this->translationDir.'/templates');

    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']], [], null, [$this->translationDir.'/trans'], [$this->translationDir.'/views']);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', '--dump-messages' => true, '--clean' => true]);
    //     $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
    //     $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    // }

    // public function testDumpTwoMessagesAndClean()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'bar' => 'bar']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
    //     $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
    //     $this->assertMatchesRegularExpression('/bar/', $tester->getDisplay());
    //     $this->assertMatchesRegularExpression('/2 messages were successfully extracted/', $tester->getDisplay());
    // }

    // public function testDumpMessagesForSpecificDomain()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--domain' => 'mydomain']);
    //     $this->assertMatchesRegularExpression('/bar/', $tester->getDisplay());
    //     $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    // }

    // public function testWriteMessages()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true]);
    //     $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    // }

    // public function testWriteMessagesInRootDirectory()
    // {
    //     $this->fs->remove($this->translationDir);
    //     $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
    //     $this->fs->mkdir($this->translationDir.'/translations');
    //     $this->fs->mkdir($this->translationDir.'/templates');

    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', '--force' => true]);
    //     $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    // }

    // public function testWriteMessagesForSpecificDomain()
    // {
    //     $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
    //     $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true, '--domain' => 'mydomain']);
    //     $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    // }

    private function createCommandTester(): CommandTester
    {
        $kernel = $this->createMock(KernelInterface::class);

        $container = new Container();

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $dispatcher = new EventDispatcher();
        // $dispatcher->addListener();

        $dispatchers = $this->createMock(ContainerInterface::class);

        $dispatchers
            ->expects($this->any())
            ->method('has')
            ->willReturn(true);

        $dispatchers
            ->expects($this->any())
            ->method('get')
            ->willReturn(true);

        $command = new EventDispatcherDebugCommand($dispatchers);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('debug:event-dispatcher'));
    }
}
