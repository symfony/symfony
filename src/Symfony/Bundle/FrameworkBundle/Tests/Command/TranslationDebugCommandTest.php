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
use Symfony\Bundle\FrameworkBundle\Command\TranslationDebugCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\ExtensionWithoutConfigTestBundle\ExtensionWithoutConfigTestBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Translator;

class TranslationDebugCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    public function testDebugMissingMessages()
    {
        $tester = $this->createCommandTester(['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        self::assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        self::assertSame(TranslationDebugCommand::EXIT_CODE_MISSING, $res);
    }

    public function testDebugUnusedMessages()
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        self::assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        self::assertSame(TranslationDebugCommand::EXIT_CODE_UNUSED, $res);
    }

    public function testDebugFallbackMessages()
    {
        $tester = $this->createCommandTester(['foo' => 'foo'], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'fr', 'bundle' => 'foo']);

        self::assertMatchesRegularExpression('/fallback/', $tester->getDisplay());
        self::assertSame(TranslationDebugCommand::EXIT_CODE_FALLBACK, $res);
    }

    public function testNoDefinedMessages()
    {
        $tester = $this->createCommandTester();
        $res = $tester->execute(['locale' => 'fr', 'bundle' => 'test']);

        self::assertMatchesRegularExpression('/No defined or extracted messages for locale "fr"/', $tester->getDisplay());
        self::assertSame(TranslationDebugCommand::EXIT_CODE_GENERAL_ERROR, $res);
    }

    public function testDebugDefaultDirectory()
    {
        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar']);
        $res = $tester->execute(['locale' => 'en']);
        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        self::assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        self::assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        self::assertSame($expectedExitStatus, $res);
    }

    public function testDebugDefaultRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar'], null, [$this->translationDir.'/trans'], [$this->translationDir.'/views']);
        $res = $tester->execute(['locale' => 'en']);

        self::assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        self::assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        self::assertSame($expectedExitStatus, $res);
    }

    public function testDebugCustomDirectory()
    {
        $this->fs->mkdir($this->translationDir.'/customDir/translations');
        $this->fs->mkdir($this->translationDir.'/customDir/templates');
        $kernel = self::createMock(KernelInterface::class);
        $kernel->expects(self::once())
            ->method('getBundle')
            ->with(self::equalTo($this->translationDir.'/customDir'))
            ->willThrowException(new \InvalidArgumentException());

        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar'], $kernel);
        $res = $tester->execute(['locale' => 'en', 'bundle' => $this->translationDir.'/customDir']);

        self::assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        self::assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        self::assertSame($expectedExitStatus, $res);
    }

    public function testDebugInvalidDirectory()
    {
        self::expectException(\InvalidArgumentException::class);
        $kernel = self::createMock(KernelInterface::class);
        $kernel->expects(self::once())
            ->method('getBundle')
            ->with(self::equalTo('dir'))
            ->willThrowException(new \InvalidArgumentException());

        $tester = $this->createCommandTester([], [], $kernel);
        $tester->execute(['locale' => 'en', 'bundle' => 'dir']);
    }

    public function testNoErrorWithOnlyMissingOptionAndNoResults()
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', '--only-missing' => true]);

        self::assertSame(Command::SUCCESS, $res);
    }

    public function testNoErrorWithOnlyUnusedOptionAndNoResults()
    {
        $tester = $this->createCommandTester(['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', '--only-unused' => true]);

        self::assertSame(Command::SUCCESS, $res);
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

    private function createCommandTester(array $extractedMessages = [], array $loadedMessages = [], KernelInterface $kernel = null, array $transPaths = [], array $codePaths = []): CommandTester
    {
        return new CommandTester($this->createCommand($extractedMessages, $loadedMessages, $kernel, $transPaths, $codePaths));
    }

    private function createCommand(array $extractedMessages = [], array $loadedMessages = [], KernelInterface $kernel = null, array $transPaths = [], array $codePaths = [], ExtractorInterface $extractor = null, array $bundles = [], array $enabledLocales = []): TranslationDebugCommand
    {
        $translator = self::createMock(Translator::class);
        $translator
            ->expects(self::any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        if (!$extractor) {
            $extractor = self::createMock(ExtractorInterface::class);
            $extractor
                ->expects(self::any())
                ->method('extract')
                ->willReturnCallback(
                    function ($path, $catalogue) use ($extractedMessages) {
                        $catalogue->add($extractedMessages);
                    }
                );
        }

        $loader = self::createMock(TranslationReader::class);
        $loader
            ->expects(self::any())
            ->method('read')
            ->willReturnCallback(
                function ($path, $catalogue) use ($loadedMessages) {
                    $catalogue->add($loadedMessages);
                }
            );

        if (null === $kernel) {
            $returnValues = [
                ['foo', $this->getBundle($this->translationDir)],
                ['test', $this->getBundle('test')],
            ];
            $kernel = self::createMock(KernelInterface::class);
            $kernel
                ->expects(self::any())
                ->method('getBundle')
                ->willReturnMap($returnValues);
        }

        $kernel
            ->expects(self::any())
            ->method('getBundles')
            ->willReturn($bundles);

        $container = new Container();
        $kernel
            ->expects(self::any())
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationDebugCommand($translator, $loader, $extractor, $this->translationDir.'/translations', $this->translationDir.'/templates', $transPaths, $codePaths, $enabledLocales);

        $application = new Application($kernel);
        $application->add($command);

        return $application->find('debug:translation');
    }

    private function getBundle($path)
    {
        $bundle = self::createMock(BundleInterface::class);
        $bundle
            ->expects(self::any())
            ->method('getPath')
            ->willReturn($path)
        ;

        return $bundle;
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
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
        $extractor = self::createMock(ExtractorInterface::class);
        $extractor
            ->expects(self::any())
            ->method('extract')
            ->willReturnCallback(
                function ($path, $catalogue) use ($extractedMessagesWithDomains) {
                    foreach ($extractedMessagesWithDomains as $domain => $message) {
                        $catalogue->add($message, $domain);
                    }
                }
            );

        $tester = new CommandCompletionTester($this->createCommand([], [], null, [], [], $extractor, [new ExtensionWithoutConfigTestBundle()], ['fr', 'nl']));
        $suggestions = $tester->complete($input);
        self::assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        yield 'locale' => [
            [''],
            ['fr', 'nl'],
        ];

        yield 'bundle' => [
            ['fr', '--domain', 'messages', ''],
            ['ExtensionWithoutConfigTestBundle', 'extension_without_config_test'],
        ];

        yield 'option --domain' => [
            ['en', '--domain', ''],
            ['messages', 'validators', 'custom_domain'],
        ];
    }
}
