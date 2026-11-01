<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Command\TranslationDebugCommand;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Translator;

class TranslationDebugCommandTest extends TestCase
{
    private Filesystem $fs;
    private string $translationDir;

    public function testDebugMissingMessages()
    {
        $tester = $this->createCommandTester(['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        $this->assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        $this->assertSame(TranslationDebugCommand::EXIT_CODE_MISSING, $res);
    }

    public function testDebugUnusedMessages()
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        $this->assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        $this->assertSame(TranslationDebugCommand::EXIT_CODE_UNUSED, $res);
    }

    public function testDebugFallbackMessages()
    {
        $tester = $this->createCommandTester(['foo' => 'foo'], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'fr', 'bundle' => 'foo']);

        $this->assertMatchesRegularExpression('/fallback/', $tester->getDisplay());
        $this->assertSame(TranslationDebugCommand::EXIT_CODE_FALLBACK, $res);
    }

    public function testNoDefinedMessages()
    {
        $tester = $this->createCommandTester();
        $res = $tester->execute(['locale' => 'fr', 'bundle' => 'test']);

        $this->assertMatchesRegularExpression('/No defined or extracted messages for locale "fr"/', $tester->getDisplay());
        $this->assertSame(TranslationDebugCommand::EXIT_CODE_GENERAL_ERROR, $res);
    }

    public function testDebugDefaultDirectory()
    {
        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar']);
        $res = $tester->execute(['locale' => 'en']);
        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        $this->assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        $this->assertSame($expectedExitStatus, $res);
    }

    public function testDebugDefaultRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->fs = new Filesystem();
        $this->translationDir = tempnam(sys_get_temp_dir(), 'sf_translation_');
        $this->fs->remove($this->translationDir);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar'], null, [$this->translationDir.'/trans'], [$this->translationDir.'/views']);
        $res = $tester->execute(['locale' => 'en']);

        $this->assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        $this->assertSame($expectedExitStatus, $res);
    }

    public function testDebugCustomDirectory()
    {
        $this->fs->mkdir($this->translationDir.'/customDir/translations');
        $this->fs->mkdir($this->translationDir.'/customDir/templates');
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getBundle')
            ->with($this->equalTo($this->translationDir.'/customDir'))
            ->willThrowException(new \InvalidArgumentException());

        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar'], $kernel);
        $res = $tester->execute(['locale' => 'en', 'bundle' => $this->translationDir.'/customDir']);

        $this->assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        $this->assertSame($expectedExitStatus, $res);
    }

    public function testDebugInvalidDirectory()
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getBundle')
            ->with($this->equalTo('dir'))
            ->willThrowException(new \InvalidArgumentException());

        $tester = $this->createCommandTester([], [], $kernel);

        $this->expectException(\InvalidArgumentException::class);

        $tester->execute(['locale' => 'en', 'bundle' => 'dir']);
    }

    public function testNoErrorWithOnlyMissingOptionAndNoResults()
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', '--only-missing' => true]);

        $this->assertSame(Command::SUCCESS, $res);
    }

    public function testNoErrorWithOnlyUnusedOptionAndNoResults()
    {
        $tester = $this->createCommandTester(['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', '--only-unused' => true]);

        $this->assertSame(Command::SUCCESS, $res);
    }

    public function testJsonFormat()
    {
        $message = "Bonjour <info>monde</info>, \"Symfony\"!\n".str_repeat('é', 50);
        $tester = $this->createCommandTester(['missing' => 'missing', 'translated' => 'translated'], ['translated' => $message, 'unused' => 'Unused']);
        $status = $tester->execute(['locale' => 'fr', '--format' => 'json'], ['decorated' => true]);

        $this->assertSame(TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED | TranslationDebugCommand::EXIT_CODE_FALLBACK, $status);
        $this->assertSame([
            ['state' => ['missing'], 'domain' => 'messages', 'id' => 'missing', 'message' => 'missing', 'fallback' => ['en' => 'missing']],
            ['state' => ['fallback'], 'domain' => 'messages', 'id' => 'translated', 'message' => $message, 'fallback' => ['en' => $message]],
            ['state' => ['unused', 'fallback'], 'domain' => 'messages', 'id' => 'unused', 'message' => 'Unused', 'fallback' => ['en' => 'Unused']],
        ], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testCsvFormat()
    {
        $message = "Bonjour <info>monde</info>, \"Symfony\"!\n".str_repeat('é', 50);
        $tester = $this->createCommandTester(['translated' => 'translated'], ['translated' => $message]);
        $status = $tester->execute(['locale' => 'fr', '--format' => 'csv'], ['decorated' => true]);

        $stream = fopen('php://memory', 'w+');
        fwrite($stream, $tester->getDisplay());
        rewind($stream);
        $this->assertSame(['state', 'domain', 'id', 'message', 'fallback.en'], fgetcsv($stream, escape: ''));
        $this->assertSame(['fallback', 'messages', 'translated', $message, $message], fgetcsv($stream, escape: ''));
        $this->assertFalse(fgetcsv($stream, escape: ''));
        fclose($stream);
        $this->assertSame(TranslationDebugCommand::EXIT_CODE_FALLBACK, $status);
    }

    #[DataProvider('provideMachineFormats')]
    public function testMachineFormatWithNoMatchingMessages(string $format)
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $this->assertSame(Command::SUCCESS, $tester->execute(['locale' => 'en', '--only-missing' => true, '--format' => $format]));
        $this->assertSame('json' === $format ? "[]\n" : "state,domain,id,message\n", $tester->getDisplay(true));
    }

    #[DataProvider('provideMachineFormats')]
    public function testMachineFormatWithoutMessages(string $format)
    {
        $tester = $this->createCommandTester();
        $status = $tester->execute(['locale' => 'en', '--format' => $format], ['capture_stderr_separately' => true]);

        $this->assertSame(TranslationDebugCommand::EXIT_CODE_GENERAL_ERROR, $status);
        $this->assertSame('json' === $format ? "[]\n" : "state,domain,id,message\n", $tester->getDisplay(true));
        $this->assertStringContainsString('No defined or extracted messages', $tester->getErrorOutput());
    }

    public function testJsonFormatFiltersMessages()
    {
        $tester = $this->createCommandTester(['missing' => 'missing'], ['unused' => 'Unused']);
        $status = $tester->execute(['locale' => 'en', '--domain' => 'messages', '--only-unused' => true, '--format' => 'json']);

        $this->assertSame(TranslationDebugCommand::EXIT_CODE_UNUSED, $status);
        $this->assertSame([
            ['state' => ['unused'], 'domain' => 'messages', 'id' => 'unused', 'message' => 'Unused', 'fallback' => []],
        ], json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR));
    }

    public static function provideMachineFormats(): iterable
    {
        yield ['json'];
        yield ['csv'];
    }

    public function testInvalidFormat()
    {
        $tester = $this->createCommandTester();
        $this->expectException(\Symfony\Component\Console\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported format "xml". Supported formats are "txt", "json" and "csv".');
        $tester->execute(['locale' => 'en', '--format' => 'xml']);
    }

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->translationDir = tempnam(sys_get_temp_dir(), 'sf_translation_');
        $this->fs->remove($this->translationDir);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->translationDir);
    }

    private function createCommandTester(array $extractedMessages = [], array $loadedMessages = [], ?KernelInterface $kernel = null, array $transPaths = [], array $codePaths = []): CommandTester
    {
        return new CommandTester($this->createCommand($extractedMessages, $loadedMessages, $kernel, $transPaths, $codePaths));
    }

    private function createCommand(array $extractedMessages = [], array $loadedMessages = [], ?KernelInterface $kernel = null, array $transPaths = [], array $codePaths = [], ?ExtractorInterface $extractor = null, array $bundles = [], array $enabledLocales = []): TranslationDebugCommand
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['en']);

        if (!$extractor) {
            $extractor = $this->createStub(ExtractorInterface::class);
            $extractor
                ->method('extract')
                ->willReturnCallback(
                    static function ($path, $catalogue) use ($extractedMessages) {
                        $catalogue->add($extractedMessages);
                    }
                );
        }

        $loader = $this->createStub(TranslationReader::class);
        $loader
            ->method('read')
            ->willReturnCallback(
                static function ($path, $catalogue) use ($loadedMessages) {
                    $catalogue->add($loadedMessages);
                }
            );

        if (null === $kernel) {
            $returnValues = [
                ['foo', $this->getBundle($this->translationDir)],
                ['test', $this->getBundle('test')],
            ];
            $kernel = $this->createStub(KernelInterface::class);
            $kernel
                ->method('getBundle')
                ->willReturnMap($returnValues);
        }

        $kernel
            ->method('getBundles')
            ->willReturn($bundles);

        $container = new Container();
        $kernel
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationDebugCommand($kernel, $translator, $loader, $extractor, $this->translationDir.'/translations', $this->translationDir.'/templates', $transPaths, $codePaths, $enabledLocales);

        $application = new Application();
        $application->addCommand($command);

        return $application->find('debug:translation');
    }

    private function getBundle($path, string $name = '', ?string $alias = null)
    {
        $bundle = $this->createStub(BundleInterface::class);
        $bundle
            ->method('getPath')
            ->willReturn($path)
        ;
        $bundle
            ->method('getName')
            ->willReturn($name)
        ;

        if (null !== $alias) {
            $extension = $this->createStub(ExtensionInterface::class);
            $extension
                ->method('getAlias')
                ->willReturn($alias)
            ;
            $bundle
                ->method('getContainerExtension')
                ->willReturn($extension)
            ;
        }

        return $bundle;
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $extractedMessagesWithDomains = [
            'messages' => [
                'foo' => 'foo',
            ],
            'validators' => [
                'foo' => 'foo',
            ],
            'custom_domain' => [
                'foo' => 'foo',
            ],
        ];
        $extractor = $this->createStub(ExtractorInterface::class);
        $extractor
            ->method('extract')
            ->willReturnCallback(
                static function ($path, $catalogue) use ($extractedMessagesWithDomains) {
                    foreach ($extractedMessagesWithDomains as $domain => $message) {
                        $catalogue->add($message, $domain);
                    }
                }
            );

        $tester = new CommandCompletionTester($this->createCommand([], [], null, [], [], $extractor, [$this->getBundle('foo', 'FooBundle', 'foo')], ['fr', 'nl']));
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield 'locale' => [
            [''],
            ['fr', 'nl'],
        ];

        yield 'bundle' => [
            ['fr', '--domain', 'messages', ''],
            ['FooBundle', 'foo'],
        ];

        yield 'option --format' => [
            ['en', '--format', ''],
            ['txt', 'json', 'csv'],
        ];

        yield 'option --domain' => [
            ['en', '--domain', ''],
            ['messages', 'validators', 'custom_domain'],
        ];
    }
}
