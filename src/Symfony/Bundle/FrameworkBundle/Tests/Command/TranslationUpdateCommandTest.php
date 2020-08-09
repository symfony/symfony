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
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesAndCleanInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf2_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', '--dump-messages' => true, '--clean' => true]);
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpTwoMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/bar/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/2 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--domain' => 'mydomain']);
        $this->assertMatchesRegularExpression('/bar/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testWriteMessages()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true]);
        $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf2_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', '--force' => true]);
        $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true, '--domain' => 'mydomain']);
        $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    }

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf2_translation', true);
        $this->fs->mkdir($this->translationDir.'/Resources/translations');
        $this->fs->mkdir($this->translationDir.'/Resources/views');
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');
    }

    protected function tearDown()
    {
        $this->fs->remove($this->translationDir);
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester($extractedMessages = [], $loadedMessages = [], HttpKernel\KernelInterface $kernel = null)
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
            if (HttpKernel\Kernel::VERSION_ID < 40000) {
                $returnValues = [
                    ['foo', true, $this->getBundle($this->translationDir)],
                    ['test', true, $this->getBundle('test')],
                ];
            }
            $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
            $kernel
                ->expects($this->any())
                ->method('getBundle')
                ->willReturnMap($returnValues);
        }

        $kernel
            ->expects($this->any())
            ->method('getRootDir')
            ->willReturn($this->translationDir);

        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->willReturn([]);

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock());

        $command = new TranslationUpdateCommand($writer, $loader, $extractor, 'en', $this->translationDir.'/translations', $this->translationDir.'/templates');

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('translation:update'));
    }

    /**
     * @group legacy
     * @expectedDeprecation Symfony\Bundle\FrameworkBundle\Command\TranslationUpdateCommand::__construct() expects an instance of "Symfony\Component\Translation\Writer\TranslationWriterInterface" as first argument since Symfony 3.4. Not passing it is deprecated and will throw a TypeError in 4.0.
     */
    public function testLegacyUpdateCommand()
    {
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $extractor = $this->getMockBuilder('Symfony\Component\Translation\Extractor\ExtractorInterface')->getMock();
        $loader = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader')->getMock();
        $writer = $this->getMockBuilder('Symfony\Component\Translation\Writer\TranslationWriter')->getMock();
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->willReturn([]);

        $container = $this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock();
        $container
            ->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['translation.extractor', 1, $extractor],
                ['translation.reader', 1, $loader],
                ['translation.writer', 1, $writer],
                ['translator', 1, $translator],
                ['kernel', 1, $kernel],
            ]);

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationUpdateCommand();
        $command->setContainer($container);

        $application = new Application($kernel);
        $application->add($command);

        $tester = new CommandTester($application->find('translation:update'));
        $tester->execute(['locale' => 'en']);

        $this->assertStringContainsString('You must choose one of --force or --dump-messages', $tester->getDisplay());
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
