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
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\ExtensionPresentBundle\ExtensionPresentBundle;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\Writer\TranslationWriter;

class TranslationUpdateCommandCompletionTest extends TestCase
{
    private $fs;
    private $translationDir;

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = $this->createCommandCompletionTester(['messages' => ['foo' => 'foo']]);

        $suggestions = $tester->complete($input);

        self::assertSame($expectedSuggestions, $suggestions);
    }

    public function provideCompletionSuggestions()
    {
        $bundle = new ExtensionPresentBundle();

        yield 'locale' => [[''], ['en', 'fr']];
        yield 'bundle' => [['en', ''], [$bundle->getName(), $bundle->getContainerExtension()->getAlias()]];
        yield 'domain with locale' => [['en', '--domain=m'], ['messages']];
        yield 'domain without locale' => [['--domain=m'], []];
        yield 'format' => [['en', '--format='], ['php', 'xlf', 'po', 'mo', 'yml', 'yaml', 'ts', 'csv', 'ini', 'json', 'res', 'xlf12', 'xlf20']];
        yield 'sort' => [['en', '--sort='], ['asc', 'desc']];
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

    private function createCommandCompletionTester($extractedMessages = [], $loadedMessages = [], KernelInterface $kernel = null, array $transPaths = [], array $codePaths = []): CommandCompletionTester
    {
        $translator = self::createMock(Translator::class);
        $translator
            ->expects(self::any())
            ->method('getFallbackLocales')
            ->willReturn(['en']);

        $extractor = self::createMock(ExtractorInterface::class);
        $extractor
            ->expects(self::any())
            ->method('extract')
            ->willReturnCallback(
                function ($path, $catalogue) use ($extractedMessages) {
                    foreach ($extractedMessages as $domain => $messages) {
                        $catalogue->add($messages, $domain);
                    }
                }
            );

        $loader = self::createMock(TranslationReader::class);
        $loader
            ->expects(self::any())
            ->method('read')
            ->willReturnCallback(
                function ($path, $catalogue) use ($loadedMessages) {
                    $catalogue->add($loadedMessages);
                }
            );

        $writer = self::createMock(TranslationWriter::class);
        $writer
            ->expects(self::any())
            ->method('getFormats')
            ->willReturn(
                ['php', 'xlf', 'po', 'mo', 'yml', 'yaml', 'ts', 'csv', 'ini', 'json', 'res']
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
            ->willReturn([new ExtensionPresentBundle()]);

        $container = new Container();
        $kernel
            ->expects(self::any())
            ->method('getContainer')
            ->willReturn($container);

        $command = new TranslationUpdateCommand($writer, $loader, $extractor, 'en', $this->translationDir.'/translations', $this->translationDir.'/templates', $transPaths, $codePaths, ['en', 'fr']);

        $application = new Application($kernel);
        $application->add($command);

        return new CommandCompletionTester($application->find('translation:extract'));
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
}
