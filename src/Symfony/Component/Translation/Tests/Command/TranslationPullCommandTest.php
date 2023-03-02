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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\Command\TranslationPullCommand;
use Symfony\Component\Translation\Dumper\XliffFileDumper;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\TranslatorBag;
use Symfony\Component\Translation\Writer\TranslationWriter;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
class TranslationPullCommandTest extends TranslationProviderTestCase
{
    private $colSize;

    protected function setUp(): void
    {
        $this->colSize = getenv('COLUMNS');
        putenv('COLUMNS='.(119 + \strlen(\PHP_EOL)));
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv($this->colSize ? 'COLUMNS='.$this->colSize : 'COLUMNS');
    }

    public function testPullNewXlf12Messages()
    {
        $arrayLoader = new ArrayLoader();
        $filenameEn = $this->createFile();
        $filenameEnIcu = $this->createFile(['say_hello' => 'Welcome, {firstname}!'], 'en', 'messages+intl-icu.%locale%.xlf');
        $filenameFr = $this->createFile(['note' => 'NOTE'], 'fr');
        $filenameFrIcu = $this->createFile(['say_hello' => 'Bonjour, {firstname}!'], 'fr', 'messages+intl-icu.%locale%.xlf');
        $locales = ['en', 'fr'];
        $domains = ['messages', 'messages+intl-icu'];

        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'new.foo' => 'newFoo',
        ], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'say_hello' => 'Welcome, {firstname}!',
        ], 'en', 'messages+intl-icu'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'new.foo' => 'nouveauFoo',
        ], 'fr'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'say_hello' => 'Bonjour, {firstname}!',
        ], 'fr', 'messages+intl-icu'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);
        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages', 'messages+intl-icu']]);

        $this->assertStringContainsString('[OK] New translations from "null" has been written locally (for "en, fr" locale(s), and "messages, messages+intl-icu"', trim($tester->getDisplay()));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>newFoo</target>
            </trans-unit>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameEn));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="1IHotcu" resname="say_hello">
                <source>say_hello</source>
                <target>Welcome, {firstname}!</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameEnIcu));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>nouveauFoo</target>
            </trans-unit>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameFr));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="1IHotcu" resname="say_hello">
                <source>say_hello</source>
                <target>Bonjour, {firstname}!</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameFrIcu));
    }

    public function testPullNewXlf20Messages()
    {
        $arrayLoader = new ArrayLoader();
        $filenameEn = $this->createFile(['note' => 'NOTE'], 'en', 'messages.%locale%.xlf', 'xlf20');
        $filenameFr = $this->createFile(['note' => 'NOTE'], 'fr', 'messages.%locale%.xlf', 'xlf20');
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'new.foo' => 'newFoo',
        ], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'new.foo' => 'nouveauFoo',
        ], 'fr'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);
        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages'], '--format' => 'xlf20']);

        $this->assertStringContainsString('[OK] New translations from "null" has been written locally (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0" encoding="utf-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="en">
  <file id="messages.en">
    <unit id="994ixRL" name="new.foo">
      <segment>
        <source>new.foo</source>
        <target>newFoo</target>
      </segment>
    </unit>
    <unit id="7bRlYkK" name="note">
      <segment>
        <source>note</source>
        <target>NOTE</target>
      </segment>
    </unit>
  </file>
</xliff>
XLIFF
            , file_get_contents($filenameEn));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0" encoding="utf-8"?>
<xliff xmlns="urn:oasis:names:tc:xliff:document:2.0" version="2.0" srcLang="en" trgLang="fr">
  <file id="messages.fr">
    <unit id="994ixRL" name="new.foo">
      <segment>
        <source>new.foo</source>
        <target>nouveauFoo</target>
      </segment>
    </unit>
    <unit id="7bRlYkK" name="note">
      <segment>
        <source>note</source>
        <target>NOTE</target>
      </segment>
    </unit>
  </file>
</xliff>
XLIFF
            , file_get_contents($filenameFr));
    }

    public function testPullForceMessages()
    {
        $arrayLoader = new ArrayLoader();
        $filenameMessagesEn = $this->createFile(['note' => 'NOTE'], 'en');
        $filenameMessagesFr = $this->createFile(['note' => 'NOTE'], 'fr');
        $filenameValidatorsEn = $this->createFile(['foo.error' => 'Wrong value'], 'en', 'validators.%locale%.xlf');
        $filenameValidatorsFr = $this->createFile(['foo.error' => 'Valeur erronée'], 'fr', 'validators.%locale%.xlf');
        $locales = ['en', 'fr'];
        $domains = ['messages', 'validators'];

        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'UPDATED NOTE',
            'new.foo' => 'newFoo',
        ], 'en', 'messages'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE MISE À JOUR',
            'new.foo' => 'nouveauFoo',
        ], 'fr', 'messages'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'foo.error' => 'Bad value',
            'bar.error' => 'Bar error',
        ], 'en', 'validators'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'foo.error' => 'Valeur invalide',
            'bar.error' => 'Bar erreur',
        ], 'fr', 'validators'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);
        $tester->execute(['--locales' => $locales, '--domains' => $domains, '--force' => true]);

        $this->assertStringContainsString('[OK] Local translations has been updated from "null" (for "en, fr" locale(s), and "messages, validators" domain(s)).', trim($tester->getDisplay()));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>UPDATED NOTE</target>
            </trans-unit>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>newFoo</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameMessagesEn));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE MISE À JOUR</target>
            </trans-unit>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>nouveauFoo</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameMessagesFr));

        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="kA4akVr" resname="foo.error">
                <source>foo.error</source>
                <target>Bad value</target>
            </trans-unit>
            <trans-unit id="OcBtn3X" resname="bar.error">
                <source>bar.error</source>
                <target>Bar error</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameValidatorsEn));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="kA4akVr" resname="foo.error">
                <source>foo.error</source>
                <target>Valeur invalide</target>
            </trans-unit>
            <trans-unit id="OcBtn3X" resname="bar.error">
                <source>bar.error</source>
                <target>Bar erreur</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameValidatorsFr));
    }

    /**
     * @requires extension intl
     */
    public function testPullForceIntlIcuMessages()
    {
        $arrayLoader = new ArrayLoader();
        $filenameEn = $this->createFile(['note' => 'NOTE'], 'en', 'messages+intl-icu.%locale%.xlf');
        $filenameFr = $this->createFile(['note' => 'NOTE'], 'fr', 'messages+intl-icu.%locale%.xlf');

        $locales = ['en', 'fr'];
        $domains = ['messages'];

        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'UPDATED NOTE',
            'new.foo' => 'newFoo',
        ], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE MISE À JOUR',
            'new.foo' => 'nouveauFoo',
        ], 'fr'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);
        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages'], '--force' => true, '--intl-icu' => true]);

        $this->assertStringContainsString('[OK] Local translations has been updated from "null" (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>UPDATED NOTE</target>
            </trans-unit>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>newFoo</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameEn));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="fr" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE MISE À JOUR</target>
            </trans-unit>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>nouveauFoo</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameFr));
    }

    public function testPullMessagesWithDefaultLocale()
    {
        $arrayLoader = new ArrayLoader();
        $filenameFr = $this->createFile(['note' => 'NOTE'], 'fr');
        $filenameEn = $this->createFile(['note' => 'NOTE']);
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'new.foo' => 'nouveauFoo',
        ], 'fr'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'new.foo' => 'newFoo',
        ], 'en'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains, 'fr');
        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages']]);

        $this->assertStringContainsString('[OK] New translations from "null" has been written locally (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="fr" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>newFoo</target>
            </trans-unit>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameEn));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="fr" target-language="fr" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>nouveauFoo</target>
            </trans-unit>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameFr));
    }

    public function testPullMessagesMultipleDomains()
    {
        $arrayLoader = new ArrayLoader();
        $filenameMessages = $this->createFile(['note' => 'NOTE']);
        $filenameDomain = $this->createFile(['note' => 'NOTE'], 'en', 'domain.%locale%.xlf');
        $locales = ['en'];
        $domains = ['messages', 'domain'];

        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'new.foo' => 'newFoo',
        ], 'en'));

        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'new.foo' => 'newFoo',
        ], 'en',
            'domain'
        ));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains, 'en');
        $tester->execute(['--locales' => ['en'], '--domains' => ['messages', 'domain']]);

        $this->assertStringContainsString('[OK] New translations from "null" has been written locally (for "en" locale(s), and "messages, domain" domain(s)).', trim($tester->getDisplay()));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>newFoo</target>
            </trans-unit>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameMessages));
        $this->assertXmlStringEqualsXmlString(<<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="en" datatype="plaintext" original="file.ext">
        <header>
            <tool tool-id="symfony" tool-name="Symfony"/>
        </header>
        <body>
            <trans-unit id="994ixRL" resname="new.foo">
                <source>new.foo</source>
                <target>newFoo</target>
            </trans-unit>
            <trans-unit id="7bRlYkK" resname="note">
                <source>note</source>
                <target>NOTE</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF
            , file_get_contents($filenameDomain));
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application();
        $application->add($this->createCommand($this->createMock(ProviderInterface::class), ['en', 'fr', 'it'], ['messages', 'validators'], 'en', ['loco', 'crowdin', 'lokalise']));

        $tester = new CommandCompletionTester($application->get('translation:pull'));
        $suggestions = $tester->complete($input);
        $this->assertSame($expectedSuggestions, $suggestions);
    }

    public static function provideCompletionSuggestions(): \Generator
    {
        yield 'provider' => [
            [''],
            ['loco', 'crowdin', 'lokalise'],
        ];

        yield '--domains' => [
            ['loco', '--domains'],
            ['messages', 'validators'],
        ];

        yield '--locales' => [
            ['loco', '--locales'],
            ['en', 'fr', 'it'],
        ];
    }

    private function createCommandTester(ProviderInterface $provider, array $locales = ['en'], array $domains = ['messages'], $defaultLocale = 'en'): CommandTester
    {
        $command = $this->createCommand($provider, $locales, $domains, $defaultLocale);
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('translation:pull'));
    }

    private function createCommand(ProviderInterface $provider, array $locales = ['en'], array $domains = ['messages'], $defaultLocale = 'en', array $providerNames = ['loco']): TranslationPullCommand
    {
        $writer = new TranslationWriter();
        $writer->addDumper('xlf', new XliffFileDumper());

        $reader = new TranslationReader();
        $reader->addLoader('xlf', new XliffFileLoader());

        return new TranslationPullCommand(
            $this->getProviderCollection($provider, $providerNames, $locales, $domains),
            $writer,
            $reader,
            $defaultLocale,
            [$this->translationAppDir.'/translations'],
            $locales
        );
    }
}
