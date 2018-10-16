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
        $tester = $this->createCommandTester(array('foo' => 'foo'));
        $tester->execute(array('locale' => 'en', 'bundle' => 'foo'));

        $this->assertRegExp('/missing/', $tester->getDisplay());
    }

    public function testDebugUnusedMessages()
    {
        $tester = $this->createCommandTester(array(), array('foo' => 'foo'));
        $tester->execute(array('locale' => 'en', 'bundle' => 'foo'));

        $this->assertRegExp('/unused/', $tester->getDisplay());
    }

    public function testDebugFallbackMessages()
    {
        $tester = $this->createCommandTester(array(), array('foo' => 'foo'));
        $tester->execute(array('locale' => 'fr', 'bundle' => 'foo'));

        $this->assertRegExp('/fallback/', $tester->getDisplay());
    }

    public function testNoDefinedMessages()
    {
        $tester = $this->createCommandTester();
        $tester->execute(array('locale' => 'fr', 'bundle' => 'test'));

        $this->assertRegExp('/No defined or extracted messages for locale "fr"/', $tester->getDisplay());
    }

    public function testDebugDefaultDirectory()
    {
        $tester = $this->createCommandTester(array('foo' => 'foo'), array('bar' => 'bar'));
        $tester->execute(array('locale' => 'en'));

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

        $tester = $this->createCommandTester(array('foo' => 'foo'), array('bar' => 'bar'));
        $tester->execute(array('locale' => 'en'));

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

        $tester = $this->createCommandTester(array('foo' => 'foo'), array('bar' => 'bar'), $kernel);
        $tester->execute(array('locale' => 'en', 'bundle' => $this->translationDir));

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
            ->will($this->throwException(new \InvalidArgumentException()));

        $tester = $this->createCommandTester(array(), array(), $kernel);
        $tester->execute(array('locale' => 'en', 'bundle' => 'dir'));
    }

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf_translation', true);
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
    private function createCommandTester($extractedMessages = array(), $loadedMessages = array(), $kernel = null)
    {
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->will($this->returnValue(array('en')));

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
            $returnValues = array(
                array('foo', $this->getBundle($this->translationDir)),
                array('test', $this->getBundle('test')),
            );
            if (HttpKernel\Kernel::VERSION_ID < 40000) {
                $returnValues = array(
                    array('foo', true, $this->getBundle($this->translationDir)),
                    array('test', true, $this->getBundle('test')),
                );
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
            ->will($this->returnValue(array()));

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
