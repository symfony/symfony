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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Command\TranslationPushCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\TranslatorBag;

class TranslationPushCommandTest extends TestCase
{
    private $fs;
    private $translationDir;

    /**
     * @dataProvider remotesProvider
     */
    public function testPushNewMessages($remotes)
    {
        $tester = $this->createCommandTester(
            ['messages' => ['new.foo' => 'newFoo']],
            ['messages' => ['old.foo' => 'oldFoo']],
            $remotes,
            ['en'],
            ['messages']
        );
        foreach ($remotes as $name => $remote) {
            $tester->execute([
                'command' => 'translation:push',
                'remote' => $name,
            ]);
            $this->assertRegExp('/New local translations are sent to/', $tester->getDisplay());
        }
    }

    public function remotesProvider()
    {
        yield [
            ['loco' => $this->getMockBuilder('Symfony\Component\Translation\Bridge\Loco\LocoRemote')->disableOriginalConstructor()->getMock()],
        ];
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

    /**
     * @return CommandTester
     */
    private function createCommandTester($remoteMessages = [], $localMessages = [], array $remotes = [], array $locales = [], array $domains = [], HttpKernel\KernelInterface $kernel = null, array $transPaths = [])
    {
        $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')
            ->disableOriginalConstructor()
            ->getMock();

        $translator
            ->expects($this->any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        $reader = $this->getMockBuilder('Symfony\Component\Translation\Reader\TranslationReader')->getMock();
        $reader
            ->expects($this->any())
            ->method('read')
            ->willReturnCallback(
                function ($path, $catalogue) use ($localMessages) {
                    $catalogue->add($localMessages);
                }
            );

        $writer = $this->getMockBuilder('Symfony\Component\Translation\Writer\TranslationWriter')->getMock();
        $writer
            ->expects($this->any())
            ->method('getFormats')
            ->willReturn(
                ['xlf', 'yml', 'yaml']
            );

        $remotesMock = $this->getMockBuilder('Symfony\Component\Translation\Remotes')
            ->setConstructorArgs([$remotes])
            ->getMock();

        /** @var MockObject $remote */
        foreach ($remotes as $name => $remote) {
            $remote
                ->expects($this->any())
                ->method('read')
                ->willReturnCallback(
                    function (array $domains, array $locales) use ($remoteMessages) {
                        $translatorBag = new TranslatorBag();
                        foreach ($locales as $locale) {
                            foreach ($domains as $domain) {
                                $translatorBag->addCatalogue((new MessageCatalogue($locale, $remoteMessages)), $domain);
                            }
                        }

                        return $translatorBag;
                    }
                );

            $remotesMock
                ->expects($this->once())
                ->method('get')->with($name)
                ->willReturnReference($remote);
        }

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

        $command = new TranslationPushCommand($remotesMock, $reader, $this->translationDir.'/translations', $transPaths, ['en', 'fr', 'nl']);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandTester($application->find('translation:push'));
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
