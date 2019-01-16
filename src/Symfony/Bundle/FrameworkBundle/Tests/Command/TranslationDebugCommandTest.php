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

    /**
     * @group legacy
     * @expectedDeprecation Storing translations in the "%ssf_translation%s/Resources/translations" directory is deprecated since Symfony 4.2, use the "%ssf_translation%s/translations" directory instead.
     * @expectedDeprecation Storing templates in the "%ssf_translation%s/Resources/views" directory is deprecated since Symfony 4.2, use the "%ssf_translation%s/templates" directory instead.
     */
    public function testDebugLegacyDefaultDirectory()
    {
        $this->fs->mkdir($this->translationDir.'/Resources/translations');
        $this->fs->mkdir($this->translationDir.'/Resources/views');

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar']);
        $tester->execute(['locale' => 'en']);

        $this->assertRegExp('/missing/', $tester->getDisplay());
        $this->assertRegExp('/unused/', $tester->getDisplay());
    }

    public function testDebugDefaultRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar']);
        $tester->execute(['locale' => 'en']);

        $this->assertRegExp('/missing/', $tester->getDisplay());
        $this->assertRegExp('/unused/', $tester->getDisplay());
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

        $tester = $this->createCommandTester(['foo' => 'foo'], ['bar' => 'bar'], $kernel);
        $tester->execute(['locale' => 'en', 'bundle' => $this->translationDir.'/customDir']);

        $this->assertRegExp('/missing/', $tester->getDisplay());
        $this->assertRegExp('/unused/', $tester->getDisplay());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDebugInvalidDirectory()
    {
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
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
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
            ->will($this->returnValue(['en']));

        $extractor = $this->getMockBuilder('Symfony\Component\Translation\Extractor\ExtractorInterface')->getMock();
        $extractor
            ->expects($this->any())
            ->method('extract')
            ->will(
                $this->returnCallback(function ($path, $catalogue) use ($extractedMessages) {
                    $catalogue->add($extractedMessages);
                })
            );

        $loader = $this->getMockBuilder('Symfony\Component\Translation\Reader\TranslationReader')->getMock();
        $loader
            ->expects($this->any())
            ->method('read')
            ->will(
                $this->returnCallback(function ($path, $catalogue) use ($loadedMessages) {
                    $catalogue->add($loadedMessages);
                })
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
                ->will($this->returnValueMap($returnValues));
        }

        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->will($this->returnValue([]));

        $container = new Container();
        $container->setParameter('kernel.root_dir', $this->translationDir);

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($container));

        $command = new TranslationDebugCommand($translator, $loader, $extractor, $this->translationDir.'/translations', $this->translationDir.'/templates');

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
            ->will($this->returnValue($path))
        ;

        return $bundle;
    }
}
