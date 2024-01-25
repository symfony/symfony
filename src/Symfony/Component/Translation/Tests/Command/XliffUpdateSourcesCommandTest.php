<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Command;

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

    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem();
        $this->translationAppDir = sprintf('%s/translation-xliff-update-source-test', sys_get_temp_dir());
        $this->fs->mkdir(sprintf('%s/translations', $this->translationAppDir));
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->translationAppDir);
    }

    public function testSourceTagsAreUpdatedInXliff1()
    {
        $originalEnContent = $this->createXliff1('en', 'foo', 'foo', 'bar-en');
        $enFile = $this->createFile($originalEnContent, 'messages.en.xlf');

        $originalFrContent = $this->createXliff1('fr', 'foo', 'foo', 'bar-fr');
        $frFile = $this->createFile($originalFrContent, 'messages.fr.xlf');

        $tester = new CommandTester($this->createCommand(enabledLocales: ['en', 'fr']));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();

        // All locales should be updated
        $expectedEnContent = $this->createXliff1('en', 'foo', 'bar-en', 'bar-en');
        $this->assertStringEqualsFile($enFile, $expectedEnContent);

        $expectedFrContent = $this->createXliff1('fr', 'foo', 'bar-en', 'bar-fr');
        $this->assertStringEqualsFile($frFile, $expectedFrContent);
    }

    public function testSourceTagsAreUpdatedInXliff2()
    {
        $originalEnContent = $this->createXliff2('en', 'foo', 'foo', 'bar-en');
        $enFile = $this->createFile($originalEnContent, 'messages.en.xlf');

        $originalFrContent = $this->createXliff2('fr', 'foo', 'foo', 'bar-fr');
        $frFile = $this->createFile($originalFrContent, 'messages.fr.xlf');

        $tester = new CommandTester($this->createCommand(enabledLocales: ['en', 'fr']));
        $tester->execute(['--format' => 'xlf20']);

        $tester->assertCommandIsSuccessful();

        // All locales should be updated
        $expectedEnContent = $this->createXliff2('en', 'foo', 'bar-en', 'bar-en');
        $this->assertStringEqualsFile($enFile, $expectedEnContent);

        $expectedFrContent = $this->createXliff2('fr', 'foo', 'bar-en', 'bar-fr');
        $this->assertStringEqualsFile($frFile, $expectedFrContent);
    }

    public function testFilesAreUpdatedOnlyForSpecifiedLocales()
    {
        $originalEnContent = $this->createXliff1('en', 'foo', 'foo', 'bar-en');
        $enFile = $this->createFile($originalEnContent, 'messages.en.xlf');

        $originalFrContent = $this->createXliff1('fr', 'foo', 'foo', 'bar-fr');
        $frFile = $this->createFile($originalFrContent, 'messages.fr.xlf');

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--locales' => ['fr']]);

        $tester->assertCommandIsSuccessful();

        // Locale fr should be updated
        $expectedFrContent = $this->createXliff1('fr', 'foo', 'bar-en', 'bar-fr');
        $this->assertStringEqualsFile($frFile, $expectedFrContent);

        // Default locale shouldn't be updated
        $this->assertStringEqualsFile($enFile, $originalEnContent);
    }

    public function testFilesAreUpdatedInOtherTranslationPaths()
    {
        $otherPath = sprintf('%s/other', $this->translationAppDir);
        $this->fs->mkdir($otherPath);

        $originalContent = $this->createXliff1('en', 'foo', 'foo', 'bar-en');
        $enFileInOtherDir = $this->createFile($originalContent, 'messages.en.xlf', 'other');
        $enFileInDefaultDir = $this->createFile($originalContent, 'messages.en.xlf');

        $tester = new CommandTester($this->createCommand([$otherPath]));
        $tester->execute(['--locales' => ['en']]);

        $tester->assertCommandIsSuccessful();

        $this->assertStringContainsString(sprintf('Updating XLIFF files in %s...', $otherPath), $tester->getDisplay());

        $expectedContent = $this->createXliff1('en', 'foo', 'bar-en', 'bar-en');

        // other/messages.en.xlf should be updated
        $this->assertStringEqualsFile($enFileInOtherDir, $expectedContent);

        // translations/messages.en.xlf should be updated
        $this->assertStringContainsString(sprintf('Updating XLIFF files in %s/translations...', $this->translationAppDir), $tester->getDisplay());
        $this->assertStringEqualsFile($enFileInDefaultDir, $expectedContent);
    }

    public function testFilesAreUpdatedOnlyForPathsArgument()
    {
        $fooDir = sprintf('%s/foo', $this->translationAppDir);
        $barDir = sprintf('%s/bar', $this->translationAppDir);

        $this->fs->mkdir([$fooDir, $barDir]);

        $originalContent = $this->createXliff1('en', 'foo', 'foo', 'bar-en');
        $fileInFooDir = $this->createFile($originalContent, 'messages.en.xlf', 'foo');
        $fileInDefaultDir = $this->createFile($originalContent, 'messages.en.xlf');

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['paths' => [$fooDir], '--locales' => ['en']]);

        $tester->assertCommandIsSuccessful();

        // foo/messages.en.xlf should be updated
        $updatedContent = $this->createXliff1('en', 'foo', 'bar-en', 'bar-en');
        $this->assertStringContainsString(sprintf('Updating XLIFF files in %s...', $fooDir), $tester->getDisplay());
        $this->assertStringEqualsFile($fileInFooDir, $updatedContent);

        // translations/messages.en.xlf shouldn't be updated
        $this->assertStringNotContainsString(sprintf('Updating XLIFF files in %s/translations...', $this->translationAppDir), $tester->getDisplay());
        $this->assertStringEqualsFile($fileInDefaultDir, $originalContent);
    }

    public function testCommandFailsIfNoTranslationPathIsAvailable()
    {
        $command = new XliffUpdateSourcesCommand(
            $this->createMock(TranslationWriterInterface::class),
            $this->createMock(TranslationReaderInterface::class),
            defaultLocale: 'en',
            transPaths: []
        );

        $tester = new CommandTester($command);

        $tester->execute(['--locales' => ['en']]);

        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('No paths specified in arguments, and no default paths provided to the command.', $tester->getDisplay());
    }

    public function testNoLocalesOptionsFailsIfNoEnabledLocalesAreAvailable()
    {
        $command = new XliffUpdateSourcesCommand(
            $this->createMock(TranslationWriterInterface::class),
            $this->createMock(TranslationReaderInterface::class),
            defaultLocale: 'en',
            transPaths: ['some/path'],
            enabledLocales: []
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('No locales provided in --locales options and no defaults provided to the command.', $tester->getDisplay());
        $this->assertEquals(Command::INVALID, $tester->getStatusCode());
    }

    public function testProcessAllDomainsByDefault()
    {
        $originalContent = $this->createXliff1('en', 'foo', 'foo', 'bar-en');
        $messagesEnFile = $this->createFile($originalContent, 'messages.en.xlf');
        $othersEnFile = $this->createFile($originalContent, 'others.en.xlf');

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--locales' => ['en']]);

        $tester->assertCommandIsSuccessful();

        // All files should be updated
        $expected = $this->createXliff1('en', 'foo', 'bar-en', 'bar-en');
        $this->assertStringEqualsFile($messagesEnFile, $expected);
        $this->assertStringEqualsFile($othersEnFile, $expected);
    }

    public function testOnlyProcessDomainsSpecifiedInOptions()
    {
        $originalContent = $this->createXliff1('en', 'foo', 'foo', 'bar-en');
        $messagesEnFile = $this->createFile($originalContent, 'messages.en.xlf');
        $othersEnFile = $this->createFile($originalContent, 'others.en.xlf');

        $tester = new CommandTester($this->createCommand());
        $tester->execute(['--locales' => ['en'], '--domains' => ['others']]);

        $tester->assertCommandIsSuccessful();

        // messages.en.xlf shouldn't be updated
        $this->assertStringEqualsFile($messagesEnFile, $originalContent);

        // others.en.xlf should be updated
        $expectedContent = $this->createXliff1('en', 'foo', 'bar-en', 'bar-en');
        $this->assertStringEqualsFile($othersEnFile, $expectedContent);
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $domainsByLocale = [
            'en' => ['messages', 'others'],
            'fr' => ['messages'],
            'it' => ['validators'],
        ];

        foreach ($domainsByLocale as $locale => $domains) {
            foreach ($domains as $domain) {
                $this->createFile($this->createXliff1($locale, 'foo', 'foo', 'bar'), sprintf('%s.%s.xlf', $domain, $locale));
            }
        }

        $application = new Application();
        $application->add($this->createCommand(enabledLocales: array_keys($domainsByLocale)));

        $tester = new CommandCompletionTester($application->get('translation:update-xliff-sources'));
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): \Generator
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

    private function createCommand(array $transPaths = [], array $enabledLocales = []): Command
    {
        $application = new Application();

        $reader = new TranslationReader();
        $reader->addLoader('xlf', new XliffFileLoader());
        $writer = new TranslationWriter();
        $writer->addDumper('xlf', new XliffFileDumper());
        $transPaths[] = sprintf('%s/translations', $this->translationAppDir);

        $command = new XliffUpdateSourcesCommand($writer, $reader, 'en', $transPaths, $enabledLocales);
        $application->add($command);

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
      <trans-unit id="ea75LoN" resname="{$resname}">
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
    <unit id="ea75LoN" name="{$name}">
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
        $filename = sprintf('%s/%s/%s', $this->translationAppDir, $directory, $filename);
        file_put_contents($filename, $content);

        return $filename;
    }
}
