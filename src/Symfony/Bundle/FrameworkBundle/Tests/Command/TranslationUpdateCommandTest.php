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
use Symfony\Bundle\FrameworkBundle\Command\TranslationUpdateCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel;

class TranslationUpdateCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    public function testDumpMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
        $this->assertRegExp('/foo/', $tester->getDisplay());
        $this->assertRegExp('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpSortedMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'asc']);
        $this->assertRegExp("/\*bar\*foo\*test/", preg_replace('/\s+/', '', $tester->getDisplay()));
        $this->assertRegExp('/3 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpReverseSortedMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'desc']);
        $this->assertRegExp("/\*test\*foo\*bar/", preg_replace('/\s+/', '', $tester->getDisplay()));
        $this->assertRegExp('/3 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpWrongSortAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'test']);
        $this->assertRegExp('/\[ERROR\] Wrong sort order/', $tester->getDisplay());
    }

    public function testDumpMessagesAndCleanInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']], [], null, [$this->translationDir.'/trans'], [$this->translationDir.'/views']);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', '--dump-messages' => true, '--clean' => true]);
        $this->assertRegExp('/foo/', $tester->getDisplay());
        $this->assertRegExp('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpTwoMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
        $this->assertRegExp('/foo/', $tester->getDisplay());
        $this->assertRegExp('/bar/', $tester->getDisplay());
        $this->assertRegExp('/2 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--domain' => 'mydomain']);
        $this->assertRegExp('/bar/', $tester->getDisplay());
        $this->assertRegExp('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testWriteMessages()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true]);
        $this->assertRegExp('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', '--force' => true]);
        $this->assertRegExp('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true, '--domain' => 'mydomain']);
        $this->assertRegExp('/Translation files were successfully updated./', $tester->getDisplay());
    }

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->translationDir);
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester($extractedMessages = [], $loadedMessages = [], HttpKernel\KernelInterface $kernel = null, array $transPaths = [], array $viewsPaths = [])
    {
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        $extractor = $this->getMockBuilder('Symfony\Component\Translation\Extractor\ExtractorInterface')->getMock();
        $extractor
            ->expects($this->any())
            ->method('extract')
            ->willReturnCallback(
                function ($path, $catalogue) use ($extractedMessages) {
                    foreach ($extractedMessages as $domain => $messages) {
                        $catalogue->add($messages, $domain);
                    }
                }
            );

        $loader = $this->getMockBuilder('Symfony\Component\Translation\Reader\TranslationReader')->getMock();
        $loader
            ->expects($this->any())
            ->method('read')
            ->willReturnCallback(
                function ($path, $catalogue) use ($loadedMessages) {
                    $catalogue->add($loadedMessages);
                }
            );

        $writer = $this->getMockBuilder('Symfony\Component\Translation\Writer\TranslationWriter')->getMock();
        $writer
            ->expects($this->any())
            ->method('getFormats')
            ->willReturn(
                ['xlf', 'yml', 'yaml']
            );

        if (null === $kernel) {
            $returnValues = [
                ['foo', $this->getBundle($this->translationDir)],
                ['test', $this->getBundle('test')],
            ];
            $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
            $kernel
                ->expects($this->any())
                ->method('getBundle')
                ->willReturnMap($returnValues);
        }

        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->willReturn([]);

        $container = new Container();
        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationUpdateCommand($writer, $loader, $extractor, 'en', $this->translationDir.'/translations', $this->translationDir.'/templates', $transPaths, $viewsPaths);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('translation:update'));
    }

    private function getBundle($path)
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\BundleInterface')->getMock();
        $bundle
            ->expects($this->any())
            ->method('getPath')
            ->willReturn($path)
        ;

        return $bundle;
    }
}
