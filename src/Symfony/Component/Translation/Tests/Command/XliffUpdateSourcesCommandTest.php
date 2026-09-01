<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Translation\Command\XliffUpdateSourcesCommand;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Reader\TranslationReaderInterface;
use Symfony\Component\Translation\Writer\TranslationWriter;
use Symfony\Component\Translation\Writer\TranslationWriterInterface;

class XliffUpdateSourcesCommandTest extends TestCase
{
    private string $translationAppDir;
    private string $defaultTranslationPath;

    private Filesystem $fs;

    private string|false $colSize;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS='.(119 + \strlen(\PHP_EOL)));
        $this->fs = new Filesystem();
        $this->translationAppDir = \sprintf('%s/translation-xliff-update-source-test', sys_get_temp_dir());
        $this->defaultTranslationPath = \sprintf('%s/translations', $this->translationAppDir);
        $this->fs->mkdir($this->defaultTranslationPath);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->translationAppDir);
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
    }

    public function testCommandUpdatesXliff1Files()
    {
        $originalEnContent = $this->createXliff1('en', 'hello', 'hello', 'Hello there');
        $enFile = $this->createFile($originalEnContent, 'messages.en.xlf');

        $originalFrContent = $this->createXliff1('fr', 'hello', 'hello', 'Bonjour !');
        $frFile = $this->createFile($originalFrContent, 'messages.fr.xlf');

        $tester = new CommandTester($this->createCommand(enabledLocales: ['en', 'fr']));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        // All locales should be updated
        $this->assertFileContainsSource('Hello there', $enFile);
        $this->assertFileContainsSource('Hello there', $frFile);
    }

    public function testCommandUpdatesXliff2Files()
    {
        $originalEnContent = $this->createXliff2('en', 'hello', 'hello', 'Hello there');
        $enFile = $this->createFile($originalEnContent, 'messages.en.xlf');

        $originalFrContent = $this->createXliff2('fr', 'hello', 'hello', 'Bonjour !');
        $frFile = $this->createFile($originalFrContent, 'messages.fr.xlf');

        $tester = new CommandTester($this->createCommand(enabledLocales: ['en', 'fr']));
        $tester->execute(['--format' => 'xlf20']);

        $tester->assertCommandIsSuccessful();

        // All locales should be updated
        $this->assertFileContainsSource('Hello there', $enFile);
        $this->assertFileContainsSource('Hello there', $frFile);
    }

    public function testCommandUpdatesOnlyProvidedLocales()
    {
        $originalEnContent = $this->createXliff1('en', 'hello', 'hello', 'Hello there');
        $enFile = $this->createFile($originalEnContent, 'messages.en.xlf');

        $originalFrContent = $this->createXliff1('fr', 'hello', 'hello', 'Bonjour !');
        $frFile = $this->createFile($originalFrContent, 'messages.fr.xlf');

        $tester = new CommandTester($this->createCommand(enabledLocales: ['en', 'fr']));
        $tester->execute(['--locales' => ['fr']]);

        $tester->assertCommandIsSuccessful();

        // Default locale shouldn't be updated
        $this->assertFileContainsSource('hello', $enFile);

        // Locale fr should be updated
        $this->assertFileContainsSource('Hello there', $frFile);
    }

    public function testCommandDoesNotRewriteFilesWhenSourcesAreUpToDate()
    {
        $originalContent = $this->createXliff1('en', 'hello', 'Hello there', 'Hello there');
        $enFile = $this->createFile($originalContent, 'messages.en.xlf');

        $tester = new CommandTester($this->createCommand(enabledLocales: ['en']));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString('already up-to-date', $tester->getDisplay());
        $this->assertStringEqualsFile($enFile, $originalContent);
    }

    public function testCommandUpdatesAllAvailablePathsByDefault()
    {
        $otherPath = \sprintf('%s/other', $this->translationAppDir);
        $this->fs->mkdir($otherPath);

        $originalContent = $this->createXliff1('en', 'hello', 'hello', 'Hello there');
        $enFileInDefaultDir = $this->createFile($originalContent, 'messages.en.xlf');
        $enFileInOtherDir = $this->createFile($originalContent, 'messages.en.xlf', 'other');

        $tester = new CommandTester($this->createCommand(additionalTransPaths: [$otherPath], enabledLocales: ['en']));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        // translations/messages.en.xlf should be updated
        $this->assertStringContainsString(
            \sprintf('Updating XLIFF files in %s...', $this->defaultTranslationPath),
            $tester->getDisplay(),
        );
        $this->assertFileContainsSource('Hello there', $enFileInDefaultDir);

        // other/messages.en.xlf should be updated
        $this->assertStringContainsString(
            \sprintf('Updating XLIFF files in %s...', $otherPath),
            $tester->getDisplay()
        );
        $this->assertFileContainsSource('Hello there', $enFileInOtherDir);
    }

    public function testCommandUpdatesOnlyProvidedPaths()
    {
        $fooDir = \sprintf('%s/foo', $this->translationAppDir);

        $this->fs->mkdir($fooDir);

        $originalContent = $this->createXliff1('en', 'hello', 'hello', 'Hello there');
        $fileInFooDir = $this->createFile($originalContent, 'messages.en.xlf', 'foo');
        $fileInDefaultDir = $this->createFile($originalContent, 'messages.en.xlf');

        $tester = new CommandTester($this->createCommand(enabledLocales: ['en']));
        $tester->execute(['paths' => [$fooDir]]);

        $tester->assertCommandIsSuccessful();

        // foo/messages.en.xlf should be updated
        $this->assertStringContainsString(
            \sprintf('Updating XLIFF files in %s...', $fooDir),
            $tester->getDisplay(),
        );
        $this->assertFileContainsSource('Hello there', $fileInFooDir);

        // translations/messages.en.xlf shouldn't be updated
        $this->assertStringNotContainsString(
            \sprintf('Updating XLIFF files in %s...', $this->defaultTranslationPath),
            $tester->getDisplay(),
        );
        $this->assertFileContainsSource('hello', $fileInDefaultDir);
    }

    public function testCommandUpdatesAllDomainsByDefault()
    {
        $originalContent = $this->createXliff1('en', 'hello', 'hello', 'Hello there');
        $messagesEnFile = $this->createFile($originalContent, 'messages.en.xlf');
        $othersEnFile = $this->createFile($originalContent, 'others.en.xlf');

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--locales' => ['en']]);

        $tester->assertCommandIsSuccessful();

        // All files should be updated
        $this->assertFileContainsSource('Hello there', $messagesEnFile);
        $this->assertFileContainsSource('Hello there', $othersEnFile);
    }

    public function testCommandOnlyUpdatesProvidedDomains()
    {
        $originalContent = $this->createXliff1('en', 'hello', 'hello', 'Hello there');
        $messagesEnFile = $this->createFile($originalContent, 'messages.en.xlf');
        $othersEnFile = $this->createFile($originalContent, 'others.en.xlf');

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--locales' => ['en'], '--domains' => ['others']]);

        $tester->assertCommandIsSuccessful();

        // messages.en.xlf shouldn't be updated
        $this->assertFileContainsSource('hello', $messagesEnFile);

        // others.en.xlf should be updated
        $this->assertFileContainsSource('Hello there', $othersEnFile);
    }

    public function testCommandFailsIfFormatIsNotSupported()
    {
        $command = new XliffUpdateSourcesCommand(
            $this->createStub(TranslationWriterInterface::class),
            $this->createStub(TranslationReaderInterface::class),
            defaultLocale: 'en',
            transPaths: []
        );

        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'unknown']);

        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString(
            'Unknown format "unknown".',
            $tester->getDisplay(),
        );
    }

    public function testCommandFailsIfNoTranslationPathIsAvailable()
    {
        $command = new XliffUpdateSourcesCommand(
            $this->createStub(TranslationWriterInterface::class),
            $this->createStub(TranslationReaderInterface::class),
            defaultLocale: 'en',
            transPaths: []
        );

        $tester = new CommandTester($command);

        $tester->execute(['--locales' => ['en']]);

        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString(
            'No paths specified in arguments, and no default paths provided to the command.',
            $tester->getDisplay(),
        );
    }

    public function testCommandFailsIfNoLocaleProvidedAndNoEnabledLocalesAreAvailable()
    {
        $command = new XliffUpdateSourcesCommand(
            $this->createStub(TranslationWriterInterface::class),
            $this->createStub(TranslationReaderInterface::class),
            defaultLocale: 'en',
            transPaths: ['some/path'],
            enabledLocales: []
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString(
            'No locales provided in --locales options and no defaults provided to the command.',
            $tester->getDisplay(),
        );
        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
    }

    #[DataProvider('provideCompletionSuggestions')]
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $domainsByLocale = [
            'en' => ['messages', 'others'],
            'fr' => ['messages'],
            'it' => ['validators'],
        ];

        foreach ($domainsByLocale as $locale => $domains) {
            foreach ($domains as $domain) {
                $this->createFile($this->createXliff1($locale, 'foo', 'foo', 'bar'), \sprintf('%s.%s.xlf', $domain, $locale));
            }
        }

        $application = new Application();
        $application->addCommand($this->createCommand(enabledLocales: array_keys($domainsByLocale)));

        $tester = new CommandCompletionTester($application->get('translation:update-xliff-sources'));
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): iterable
    {
        yield '--locales' => [
            ['--locales'],
            ['en', 'fr', 'it'],
        ];

        yield '--domains' => [
            ['--locales', 'fr', '--locales', 'it', '--domains'],
            ['messages', 'validators'],
        ];
    }

    private function createCommand(array $additionalTransPaths = [], array $enabledLocales = []): Command
    {
        $application = new Application();

        $reader = new TranslationReader();
        $reader->addLoader('xlf', new XliffFileLoader());
        $writer = new TranslationWriter();
        $writer->addDumper('xlf', new XliffFileDumper());
        $additionalTransPaths[] = $this->defaultTranslationPath;

        $command = new XliffUpdateSourcesCommand($writer, $reader, 'en', $additionalTransPaths, $enabledLocales);
        $application->addCommand($command);

        return $command;
    }

    private function createXliff1(string $locale, string $resname, string $source, string $target): string
    {
        return <<<XLIFF
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:1.2" version="1.2">
              <file source-language="en" target-language="{$locale}" datatype="plaintext" original="file.ext">
                <header>
                  <tool tool-id="symfony" tool-name="Symfony"/>
                </header>
                <body>
                  <trans-unit id="some-id" resname="{$resname}">
                    <source>{$source}</source>
                    <target>{$target}</target>
                  </trans-unit>
                </body>
              </file>
            </xliff>

            XLIFF;
    }

    private function createXliff2(string $locale, string $name, string $source, string $target, string $domain = 'messages'): string
    {
        return <<<XLIFF
            <?xml version="1.0" encoding="utf-8"?>
            <xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="{$locale}">
              <file id="{$domain}.{$locale}">
                <unit id="some-id" name="{$name}">
                  <segment>
                    <source>{$source}</source>
                    <target>{$target}</target>
                  </segment>
                </unit>
              </file>
            </xliff>

            XLIFF;
    }

    private function createFile(string $content, string $filename, string $directory = 'translations'): string
    {
        $filename = \sprintf('%s/%s/%s', $this->translationAppDir, $directory, $filename);
        file_put_contents($filename, $content);

        return $filename;
    }

    private function assertFileContainsSource(string $expectedSource, string $file): void
    {
        $this->assertStringContainsString("<source>{$expectedSource}</source>", file_get_contents($file));
    }
}
