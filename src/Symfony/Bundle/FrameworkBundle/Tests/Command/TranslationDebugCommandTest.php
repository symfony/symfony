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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;

class TranslationDebugCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    public function testDebugMissingMessages()
    {
        $tester = $this->createCommandTester(['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        $this->assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        $this->assertEquals(TranslationDebugCommand::EXIT_CODE_MISSING, $res);
    }

    public function testDebugUnusedMessages()
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        $this->assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        $this->assertEquals(TranslationDebugCommand::EXIT_CODE_UNUSED, $res);
    }

    public function testDebugFallbackMessages()
    {
        $tester = $this->createCommandTester(['foo' => 'foo'], ['foo' => 'foo']);
        $res = $tester->execute(['locale' => 'fr', 'bundle' => 'foo']);

        $this->assertMatchesRegularExpression('/fallback/', $tester->getDisplay());
        $this->assertEquals(TranslationDebugCommand::EXIT_CODE_FALLBACK, $res);
    }

    public function testNoDefinedMessages()
    {
        $tester = $this->createCommandTester();
        $res = $tester->execute(['locale' => 'fr', 'bundle' => 'test']);

        $this->assertMatchesRegularExpression('/No defined or extracted messages for locale "fr"/', $tester->getDisplay());
        $this->assertEquals(TranslationDebugCommand::EXIT_CODE_GENERAL_ERROR, $res);
    }

    public function testDebugDefaultDirectory()
    {
        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar']);
        $res = $tester->execute(['locale' => 'en']);
        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        $this->assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        $this->assertEquals($expectedExitStatus, $res);
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
        $this->assertEquals($expectedExitStatus, $res);
    }

    public function testDebugCustomDirectory()
    {
        $this->fs->mkdir($this->translationDir.'/customDir/translations');
        $this->fs->mkdir($this->translationDir.'/customDir/templates');
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel->expects($this->once())
            ->method('getBundle')
            ->with($this->equalTo($this->translationDir.'/customDir'))
            ->willThrowException(new \InvalidArgumentException());

        $expectedExitStatus = TranslationDebugCommand::EXIT_CODE_MISSING | TranslationDebugCommand::EXIT_CODE_UNUSED;

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar'], $kernel);
        $res = $tester->execute(['locale' => 'en', 'bundle' => $this->translationDir.'/customDir']);

        $this->assertMatchesRegularExpression('/missing/', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/unused/', $tester->getDisplay());
        $this->assertEquals($expectedExitStatus, $res);
    }

    public function testDebugInvalidDirectory()
    {
        $this->expectException('InvalidArgumentException');
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel->expects($this->once())
            ->method('getBundle')
            ->with($this->equalTo('dir'))
            ->willThrowException(new \InvalidArgumentException());

        $tester = $this->createCommandTester([], [], $kernel);
        $tester->execute(['locale' => 'en', 'bundle' => 'dir']);
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

    private function createCommandTester($extractedMessages = [], $loadedMessages = [], $kernel = null, array $transPaths = [], array $viewsPaths = []): CommandTester
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
                    $catalogue->add($extractedMessages);
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

        $command = new TranslationDebugCommand($translator, $loader, $extractor, $this->translationDir.'/translations', $this->translationDir.'/templates', $transPaths, $viewsPaths);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('debug:translation'));
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
