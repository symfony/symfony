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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel;

class TranslationDebugCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    public function testDebugMissingMessages()
    {
        $tester = $this->createCommandTester(['foo' => 'foo']);
        $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        $this->assertRegExp('/missing/', $tester->getDisplay());
    }

    public function testDebugUnusedMessages()
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $tester->execute(['locale' => 'en', 'bundle' => 'foo']);

        $this->assertRegExp('/unused/', $tester->getDisplay());
    }

    public function testDebugFallbackMessages()
    {
        $tester = $this->createCommandTester([], ['foo' => 'foo']);
        $tester->execute(['locale' => 'fr', 'bundle' => 'foo']);

        $this->assertRegExp('/fallback/', $tester->getDisplay());
    }

    public function testNoDefinedMessages()
    {
        $tester = $this->createCommandTester();
        $tester->execute(['locale' => 'fr', 'bundle' => 'test']);

        $this->assertRegExp('/No defined or extracted messages for locale "fr"/', $tester->getDisplay());
    }

    public function testDebugDefaultDirectory()
    {
        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar']);
        $tester->execute(['locale' => 'en']);

        $this->assertRegExp('/missing/', $tester->getDisplay());
        $this->assertRegExp('/unused/', $tester->getDisplay());
    }

    public function testDebugDefaultRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf2_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar']);
        $tester->execute(['locale' => 'en']);

        $this->assertRegExp('/missing/', $tester->getDisplay());
        $this->assertRegExp('/unused/', $tester->getDisplay());
    }

    public function testDebugCustomDirectory()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel->expects($this->once())
            ->method('getBundle')
            ->with($this->equalTo($this->translationDir))
            ->willThrowException(new \InvalidArgumentException());

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar'], $kernel);
        $tester->execute(['locale' => 'en', 'bundle' => $this->translationDir]);

        $this->assertRegExp('/missing/', $tester->getDisplay());
        $this->assertRegExp('/unused/', $tester->getDisplay());
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
    private function createCommandTester($extractedMessages = [], $loadedMessages = [], $kernel = null)
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

        $command = new TranslationDebugCommand($translator, $loader, $extractor, $this->translationDir.'/translations', $this->translationDir.'/templates');

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('debug:translation'));
    }

    /**
     * @group legacy
     * @expectedDeprecation Symfony\Bundle\FrameworkBundle\Command\TranslationDebugCommand::__construct() expects an instance of "Symfony\Component\Translation\TranslatorInterface" as first argument since Symfony 3.4. Not passing it is deprecated and will throw a TypeError in 4.0.
     */
    public function testLegacyDebugCommand()
    {
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();
        $extractor = $this->getMockBuilder('Symfony\Component\Translation\Extractor\ExtractorInterface')->getMock();
        $loader = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader')->getMock();
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
                ['translator', 1, $translator],
                ['kernel', 1, $kernel],
            ]);

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationDebugCommand();
        $command->setContainer($container);

        $application = new Application($kernel);
        $application->add($command);

        $tester = new CommandTester($application->find('debug:translation'));
        $tester->execute(['locale' => 'en']);

        $this->assertStringContainsString('No defined or extracted', $tester->getDisplay());
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
