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
        $tester = $this->createCommandTester(array('messages' => array('foo' => 'foo')));
        $tester->execute(array('command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true));
        $this->assertRegExp('/foo/', $tester->getDisplay());
        $this->assertRegExp('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesAndCleanInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf2_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(array('messages' => array('foo' => 'foo')));
        $tester->execute(array('command' => 'translation:update', 'locale' => 'en', '--dump-messages' => true, '--clean' => true));
        $this->assertRegExp('/foo/', $tester->getDisplay());
        $this->assertRegExp('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testDumpTwoMessagesAndClean()
    {
        $tester = $this->createCommandTester(array('messages' => array('foo' => 'foo', 'bar' => 'bar')));
        $tester->execute(array('command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true));
        $this->assertRegExp('/foo/', $tester->getDisplay());
        $this->assertRegExp('/bar/', $tester->getDisplay());
        $this->assertRegExp('/2 messages were successfully extracted/', $tester->getDisplay());
    }

    public function testDumpMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(array('messages' => array('foo' => 'foo'), 'mydomain' => array('bar' => 'bar')));
        $tester->execute(array('command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--dump-messages' => true, '--clean' => true, '--domain' => 'mydomain'));
        $this->assertRegExp('/bar/', $tester->getDisplay());
        $this->assertRegExp('/1 message was successfully extracted/', $tester->getDisplay());
    }

    public function testWriteMessages()
    {
        $tester = $this->createCommandTester(array('messages' => array('foo' => 'foo')));
        $tester->execute(array('command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true));
        $this->assertRegExp('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesInRootDirectory()
    {
        $this->fs->remove($this->translationDir);
        $this->translationDir = sys_get_temp_dir().'/'.uniqid('sf2_translation', true);
        $this->fs->mkdir($this->translationDir.'/translations');
        $this->fs->mkdir($this->translationDir.'/templates');

        $tester = $this->createCommandTester(array('messages' => array('foo' => 'foo')));
        $tester->execute(array('command' => 'translation:update', 'locale' => 'en', '--force' => true));
        $this->assertRegExp('/Translation files were successfully updated./', $tester->getDisplay());
    }

    public function testWriteMessagesForSpecificDomain()
    {
        $tester = $this->createCommandTester(array('messages' => array('foo' => 'foo'), 'mydomain' => array('bar' => 'bar')));
        $tester->execute(array('command' => 'translation:update', 'locale' => 'en', 'bundle' => 'foo', '--force' => true, '--domain' => 'mydomain'));
        $this->assertRegExp('/Translation files were successfully updated./', $tester->getDisplay());
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
    private function createCommandTester($extractedMessages = array(), $loadedMessages = array(), HttpKernel\KernelInterface $kernel = null)
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
                    foreach ($extractedMessages as $domain => $messages) {
                        $catalogue->add($messages, $domain);
                    }
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

        $writer = $this->getMockBuilder('Symfony\Component\Translation\Writer\TranslationWriter')->getMock();
        $writer
            ->expects($this->any())
            ->method('getFormats')
            ->will(
                $this->returnValue(array('xlf', 'yml'))
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
            ->method('getRootDir')
            ->will($this->returnValue($this->translationDir));

        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->will($this->returnValue(array()));

        $kernel
            ->expects($this->any())
            ->method('getContainer')
            ->will($this->returnValue($this->getMockBuilder('Symfony\Component\DependencyInjection\ContainerInterface')->getMock()));

        $command = new TranslationUpdateCommand($writer, $loader, $extractor, 'en', $this->translationDir.'/translations', $this->translationDir.'/templates');

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('translation:update'));
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
