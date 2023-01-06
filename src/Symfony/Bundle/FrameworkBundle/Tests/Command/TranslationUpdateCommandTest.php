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
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Writer\TranslationWriter;

class TranslationUpdateCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    public function testDumpMessagesAndCleanWithDeprecatedCommandName()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesAsTreeAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--as-tree' => 1]);
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpSortedMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'asc']);
        $this->assertMatchesRegularExpression("/\*bar\*foo\*test/", preg_replace('/\s+/', '', $tester->getDisplay()));
        $this->assertMatchesRegularExpression('/3 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpReverseSortedMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'desc']);
        $this->assertMatchesRegularExpression("/\*test\*foo\*bar/", preg_replace('/\s+/', '', $tester->getDisplay()));
        $this->assertMatchesRegularExpression('/3 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpSortWithoutValueAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort']);
        $this->assertMatchesRegularExpression("/\*bar\*foo\*test/", preg_replace('/\s+/', '', $tester->getDisplay()));
        $this->assertMatchesRegularExpression('/3 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpWrongSortAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'test' => 'test', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--sort' => 'test']);
        $this->assertMatchesRegularExpression('/\[ERROR\] Wrong sort order/', $tester->getDisplay());
    }

    public function testDumpMessagesAndCleanInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']], [], null, [$this->translationDir.'/trans'], [$this->translationDir.'/views']);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', '--dump-messages' => true, '--clean' => true]);
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpTwoMessagesAndClean()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo', 'bar' => 'bar']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true]);
        $this->assertMatchesRegularExpression('/foo/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/bar/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/2 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--domain' => 'mydomain']);
        $this->assertMatchesRegularExpression('/bar/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testWriteMessages()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--force' => true]);
        $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', '--force' => true]);
        $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(['messages' => ['foo' => 'foo'], 'mydomain' => ['bar' => 'bar']]);
        $tester->execute(['command' => 'translation:extract', 'locale' => 'en', 'bundle' => 'foo', '--force' => true, '--domain' => 'mydomain']);
        $this->assertMatchesRegularExpression('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testFilterDuplicateTransPaths()
    {
        $transPaths = [
            $this->translationDir.'/a/test/folder/with/a/subfolder',
            $this->translationDir.'/a/test/folder/',
            $this->translationDir.'/a/test/folder/with/a/subfolder/and/a/file.txt',
            $this->translationDir.'/a/different/test/folder',
        ];

        foreach ($transPaths as $transPath) {
            if (realpath($transPath)) {
                continue;
            }

            if (preg_match('/\.[a-z]+$/', $transPath)) {
                if (!realpath(\dirname($transPath))) {
                    mkdir(\dirname($transPath), 0777, true);
                }

                touch($transPath);
            } else {
                mkdir($transPath, 0777, true);
            }
        }

        $command = $this->createMock(TranslationUpdateCommand::class);

        $method = new \ReflectionMethod(TranslationUpdateCommand::class, 'filterDuplicateTransPaths');
        $method->setAccessible(true);

        $filteredTransPaths = $method->invoke($command, $transPaths);

        $expectedPaths = [
            realpath($this->translationDir.'/a/different/test/folder'),
            realpath($this->translationDir.'/a/test/folder'),
        ];

        $this->assertEquals($expectedPaths, $filteredTransPaths);
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

    private function createCommandTester($extractedMessages = [], $loadedMessages = [], KernelInterface $kernel = null, array $transPaths = [], array $codePaths = []): CommandTester
    {
        $translator = $this->createMock(Translator::class);
        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        $extractor = $this->createMock(ExtractorInterface::class);
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

        $loader = $this->createMock(TranslationReader::class);
        $loader
            ->expects($this->any())
            ->method('read')
            ->willReturnCallback(
                function ($path, $catalogue) use ($loadedMessages) {
                    $catalogue->add($loadedMessages);
                }
            );

        $writer = $this->createMock(TranslationWriter::class);
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
            $kernel = $this->createMock(KernelInterface::class);
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

        $command = new TranslationUpdateCommand($writer, $loader, $extractor, 'en', $this->translationDir.'/translations', $this->translationDir.'/templates', $transPaths, $codePaths);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('translation:extract'));
    }

    private function getBundle($path)
    {
        $bundle = $this->createMock(BundleInterface::class);
        $bundle
            ->expects($this->any())
            ->method('getPath')
            ->willReturn($path)
        ;

        return $bundle;
    }
}
