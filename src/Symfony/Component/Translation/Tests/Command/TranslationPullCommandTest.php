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
    public function testPullNewXlf12Messages()
    {
        $arrayLoader = new ArrayLoader();
        $filenameEn = $this->createFile();
        $filenameFr = $this->createFile(['note' => 'NOTE'], 'fr');
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
        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages']]);

        $this->assertStringContainsString('[OK] New translations from "null"', trim($tester->getDisplay()));
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

        $this->assertStringContainsString('[OK] New translations from "null"', trim($tester->getDisplay()));
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
        $filenameEn = $this->createFile();
        $filenameFr = $this->createFile(['note' => 'NOTE'], 'fr');
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
        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages'], '--force' => true]);

        $this->assertStringContainsString('[OK] Local translations has been updated from "null"', trim($tester->getDisplay()));
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

        $this->assertStringContainsString('[OK] Local translations has been updated from "null"', trim($tester->getDisplay()));
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

    private function createCommandTester(ProviderInterface $provider, array $locales = ['en'], array $domains = ['messages']): CommandTester
    {
        $writer = new TranslationWriter();
        $writer->addDumper('xlf', new XliffFileDumper());

        $reader = new TranslationReader();
        $reader->addLoader('xlf', new XliffFileLoader());

        $command = new TranslationPullCommand(
            $this->getProviderCollection($provider, $locales, $domains),
            $writer,
            $reader,
            'en',
            [$this->translationAppDir.'/translations']
        );
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('translation:pull'));
    }
}
