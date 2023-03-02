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
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
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
        $this->expectException(\InvalidArgumentException::class);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())
            ->method('getBundle')
            ->with($this->equalTo('dir'))
            ->willThrowException(new \InvalidArgumentException());

        $tester = $this->createCommandTester([], [], $kernel);
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
        $translator = $this->createMock(Translator::class);
        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        if (!$extractor) {
            $extractor = $this->createMock(ExtractorInterface::class);
            $extractor
                ->expects($this->any())
                ->method('extract')
                ->willReturnCallback(
                    function ($path, $catalogue) use ($extractedMessages) {
                        $catalogue->add($extractedMessages);
                    }
                );
        }

        $loader = $this->createMock(TranslationReader::class);
        $loader
            ->expects($this->any())
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
            $kernel = $this->createMock(KernelInterface::class);
            $kernel
                ->expects($this->any())
                ->method('getBundle')
                ->willReturnMap($returnValues);
        }

        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->willReturn($bundles);

        $container = new Container();
        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationDebugCommand($translator, $loader, $extractor, $this->translationDir.'/translations', $this->translationDir.'/templates', $transPaths, $codePaths, $enabledLocales);

        $application = new Application($kernel);
        $application->add($command);

        return $application->find('debug:translation');
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
        $extractor = $this->createMock(ExtractorInterface::class);
        $extractor
            ->expects($this->any())
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
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions()
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
