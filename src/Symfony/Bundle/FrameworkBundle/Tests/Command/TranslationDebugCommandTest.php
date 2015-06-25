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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Command\TranslationDebugCommand;
use Symfony\Component\Filesystem\Filesystem;

class TranslationDebugCommandTest extends \PHPUnit_Framework_TestCase
{
    private $fs;
    private $translationDir;

    public function testDebugMissingMessages()
    {
        $tester = $this->createCommandTester($this->getContainer(array('foo' => 'foo')));
        $tester->execute(array('locale' => 'en', 'bundle' => 'foo'));

        $this->assertRegExp('/x (\s|\|)+foo/', $tester->getDisplay(), 'Display x in case of missing message');
    }

    public function testDebugUnusedMessages()
    {
        $tester = $this->createCommandTester($this->getContainer(array(), array('foo' => 'foo')));
        $tester->execute(array('locale' => 'en', 'bundle' => 'foo'));

        $this->assertRegExp('/o (\s|\|)+foo/', $tester->getDisplay(), 'Display o in case of unused message');
    }

    public function testDebugFallbackMessages()
    {
        $tester = $this->createCommandTester($this->getContainer(array(), array('foo' => 'foo')));
        $tester->execute(array('locale' => 'fr', 'bundle' => 'foo'));

        $this->assertRegExp('/= (\s|\|)+foo/', $tester->getDisplay(), 'Display = in case of fallback message');
    }

    public function testNoDefinedMessages()
    {
        $tester = $this->createCommandTester($this->getContainer());
        $tester->execute(array('locale' => 'fr', 'bundle' => 'test'));

        $this->assertRegExp('/^No defined or extracted messages for locale "fr"/', $tester->getDisplay());
    }

    protected function setUp()
    {
        $this->fs = new Filesystem();
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf2_translation');
        $this->fs->mkdir($this->translationDir.'/Resources/translations');
        $this->fs->mkdir($this->translationDir.'/Resources/views');
    }

    protected function tearDown()
    {
        $this->fs->remove($this->translationDir);
    }

    /**
     * @return CommandTester
     */
    private function createCommandTester($container)
    {
        $command = new TranslationDebugCommand();
        $command->setContainer($container);

        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('debug:translation'));
    }

    private function getContainer($extractedMessages = array(), $loadedMessages = array())
    {
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->will($this->returnValue(array('en')));

        $extractor = $this->getMock('Symfony\Component\Translation\Extractor\ExtractorInterface');
        $extractor
            ->expects($this->any())
            ->method('extract')
            ->will(
                $this->returnCallback(function ($path, $catalogue) use ($extractedMessages) {
                  $catalogue->add($extractedMessages);
                })
            );

        $loader = $this->getMock('Symfony\Bundle\FrameworkBundle\Translation\TranslationLoader');
        $loader
            ->expects($this->any())
            ->method('loadMessages')
            ->will(
                $this->returnCallback(function ($path, $catalogue) use ($loadedMessages) {
                  $catalogue->add($loadedMessages);
                })
            );

        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->will($this->returnValueMap(array(
                array('foo', true, $this->getBundle($this->translationDir)),
                array('test', true, $this->getBundle('test')),
            )));

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap(array(
                array('translation.extractor', 1, $extractor),
                array('translation.loader', 1, $loader),
                array('translator', 1, $translator),
                array('kernel', 1, $kernel),
            )));

        return $container;
    }

    private function getBundle($path)
    {
        $bundle = $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface');
        $bundle
            ->expects($this->any())
            ->method('getPath')
            ->will($this->returnValue($path))
        ;

        return $bundle;
    }
}
