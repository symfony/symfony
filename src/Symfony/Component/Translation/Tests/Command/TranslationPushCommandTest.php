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
use Symfony\Component\Translation\Command\TranslationPushCommand;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Provider\FilteringProvider;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Provider\TranslationProviderCollection;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\TranslatorBag;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
class TranslationPushCommandTest extends TranslationProviderTestCase
{
    private string|false $colSize;

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

    public function testPushNewMessages()
    {
        $arrayLoader = new ArrayLoader();
        $xliffLoader = new XliffFileLoader();
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        // Simulate existing messages on Provider
        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'fr'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        // Create local files, with a new message
        $filenameEn = $this->createFile([
            'note' => 'NOTE',
            'new.foo' => 'newFoo',
        ]);
        $filenameFr = $this->createFile([
            'note' => 'NOTE',
            'new.foo' => 'nouveauFoo',
        ], 'fr');
        $localTranslatorBag = new TranslatorBag();
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameEn, 'en'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameFr, 'fr'));

        $provider->expects($this->once())
            ->method('write')
            ->with($localTranslatorBag->diff($providerReadTranslatorBag));

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);

        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages']]);

        $this->assertStringContainsString('[OK] New local translations has been sent to "null" (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
    }

    public function testPushNewIntlIcuMessages()
    {
        $arrayLoader = new ArrayLoader();
        $xliffLoader = new XliffFileLoader();
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        // Simulate existing messages on Provider
        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'fr'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        // Create local files, with a new message
        $filenameEn = $this->createFile([
            'note' => 'NOTE',
            'new.foo' => 'newFooIntlIcu',
        ], 'en', 'messages+intl-icu.%locale%.xlf');
        $filenameFr = $this->createFile([
            'note' => 'NOTE',
            'new.foo' => 'nouveauFooIntlIcu',
        ], 'fr', 'messages+intl-icu.%locale%.xlf');
        $localTranslatorBag = new TranslatorBag();
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameEn, 'en'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameFr, 'fr'));

        $provider->expects($this->once())
            ->method('write')
            ->with($localTranslatorBag->diff($providerReadTranslatorBag));

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);

        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages']]);

        $this->assertStringContainsString('[OK] New local translations has been sent to "null" (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
    }

    public function testPushForceMessages()
    {
        $xliffLoader = new XliffFileLoader();
        $filenameMessagesEn = $this->createFile([
            'note' => 'NOTE UPDATED',
            'new.foo' => 'newFoo',
        ], 'en');
        $filenameMessagesFr = $this->createFile([
            'note' => 'NOTE MISE À JOUR',
            'new.foo' => 'nouveauFoo',
        ], 'fr');
        $filenameValidatorsEn = $this->createFile([
            'foo.error' => 'Wrong value',
            'bar.success' => 'Form valid!',
        ], 'en', 'validators.%locale%.xlf');
        $filenameValidatorsFr = $this->createFile([
            'foo.error' => 'Valeur erronée',
            'bar.success' => 'Formulaire valide !',
        ], 'fr', 'validators.%locale%.xlf');
        $locales = ['en', 'fr'];
        $domains = ['messages', 'validators'];

        $provider = $this->createMock(ProviderInterface::class);

        $localTranslatorBag = new TranslatorBag();
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameMessagesEn, 'en', 'messages'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameMessagesFr, 'fr', 'messages'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameValidatorsEn, 'en', 'validators'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameValidatorsFr, 'fr', 'validators'));

        $provider->expects($this->once())
            ->method('write')
            ->with($localTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);

        $tester->execute(['--locales' => $locales, '--domains' => $domains, '--force' => true]);

        $this->assertStringContainsString('[OK] All local translations has been sent to "null" (for "en, fr" locale(s), and "messages, validators" domain(s)).', trim($tester->getDisplay()));
    }

    public function testDeleteMissingMessages()
    {
        $xliffLoader = new XliffFileLoader();
        $arrayLoader = new ArrayLoader();
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        // Simulate existing messages on Provider.
        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'obsolete.foo' => 'obsoleteFoo',
        ], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'obsolete.foo' => 'obsolèteFoo',
        ], 'fr'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->any())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        // Create local bag, with a missing message.
        $localTranslatorBag = new TranslatorBag();
        $localTranslatorBag->addCatalogue($xliffLoader->load($this->createFile(), 'en'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($this->createFile(['note' => 'NOTE'], 'fr'), 'fr'));

        $missingTranslatorBag = $providerReadTranslatorBag->diff($localTranslatorBag);

        $provider->expects($this->once())
            ->method('delete')
            ->with($missingTranslatorBag);

        // Read provider translations again, after missing translations deletion,
        // to avoid push freshly deleted translations.
        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'fr'));

        $provider->expects($this->any())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $provider->expects($this->once())
            ->method('write')
            ->with($localTranslatorBag->diff($providerReadTranslatorBag));

        $provider->expects($this->exactly(2))
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);

        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages'], '--delete-missing' => true]);

        $this->assertStringContainsString('[OK] Missing translations on "null" has been deleted (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
        $this->assertStringContainsString('[OK] New local translations has been sent to "null" (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
    }

    public function testPushForceAndDeleteMissingMessages()
    {
        $xliffLoader = new XliffFileLoader();
        $arrayLoader = new ArrayLoader();
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        // Simulate existing messages on Provider.
        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'obsolete.foo' => 'obsoleteFoo',
        ], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load([
            'note' => 'NOTE',
            'obsolete.foo' => 'obsolèteFoo',
        ], 'fr'));

        $provider = $this->createMock(ProviderInterface::class);
        $provider->expects($this->any())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        // Create local bag, with a missing message, an updated one and a new one.
        $localTranslatorBag = new TranslatorBag();
        $localTranslatorBag->addCatalogue($xliffLoader->load($this->createFile(['note' => 'NOTE UPDATED', 'note2' => 'NOTE 2']), 'en'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($this->createFile(['note' => 'NOTE MISE À JOUR', 'note2' => 'NOTE 2'], 'fr'), 'fr'));

        $missingTranslatorBag = $providerReadTranslatorBag->diff($localTranslatorBag);

        $provider->expects($this->once())
            ->method('delete')
            ->with($missingTranslatorBag);

        // Read provider translations again, after missing translations deletion,
        // to avoid push freshly deleted translations.
        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'fr'));

        $provider->expects($this->any())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);

        $translationBagToWrite = $localTranslatorBag->diff($providerReadTranslatorBag);
        $translationBagToWrite->addBag($localTranslatorBag->intersect($providerReadTranslatorBag));

        $provider->expects($this->once())
            ->method('write')
            ->with($translationBagToWrite);

        $provider->expects($this->exactly(2))
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);

        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages'], '--force' => true, '--delete-missing' => true]);

        $this->assertStringContainsString('[OK] Missing translations on "null" has been deleted (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
        $this->assertStringContainsString('[OK] All local translations has been sent to "null" (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
    }

    public function testPushWithProviderDomains()
    {
        $arrayLoader = new ArrayLoader();
        $xliffLoader = new XliffFileLoader();
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        // Simulate existing messages on Provider
        $providerReadTranslatorBag = new TranslatorBag();
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'en'));
        $providerReadTranslatorBag->addCatalogue($arrayLoader->load(['note' => 'NOTE'], 'fr'));

        $provider = $this->createMock(FilteringProvider::class);
        $provider->expects($this->once())
            ->method('read')
            ->with($domains, $locales)
            ->willReturn($providerReadTranslatorBag);
        $provider->expects($this->once())
            ->method('getDomains')
            ->willReturn(['messages']);

        $filenameEn = $this->createFile([
            'note' => 'NOTE',
            'new.foo' => 'newFoo',
        ]);
        $filenameFr = $this->createFile([
            'note' => 'NOTE',
            'new.foo' => 'nouveauFoo',
        ], 'fr');
        $localTranslatorBag = new TranslatorBag();
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameEn, 'en'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameFr, 'fr'));

        $provider->expects($this->once())
            ->method('write')
            ->with($localTranslatorBag->diff($providerReadTranslatorBag));

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $reader = new TranslationReader();
        $reader->addLoader('xlf', new XliffFileLoader());

        $command = new TranslationPushCommand(
            new TranslationProviderCollection([
                'loco' => $provider,
            ]),
            $reader,
            [$this->translationAppDir.'/translations'],
            $locales
        );

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->find('translation:push'));

        $tester->execute(['--locales' => ['en', 'fr']]);

        $this->assertStringContainsString('[OK] New local translations has been sent to "null" (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $application = new Application();
        $application->add($this->createCommand($this->createMock(ProviderInterface::class), ['en', 'fr', 'it'], ['messages', 'validators'], ['loco', 'crowdin', 'lokalise']));

        $tester = new CommandCompletionTester($application->get('translation:push'));
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

    private function createCommandTester(ProviderInterface $provider, array $locales = ['en'], array $domains = ['messages']): CommandTester
    {
        $command = $this->createCommand($provider, $locales, $domains);
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('translation:push'));
    }

    private function createCommand(ProviderInterface $provider, array $locales = ['en'], array $domains = ['messages'], array $providerNames = ['loco']): TranslationPushCommand
    {
        $reader = new TranslationReader();
        $reader->addLoader('xlf', new XliffFileLoader());

        return new TranslationPushCommand(
            $this->getProviderCollection($provider, $providerNames, $locales, $domains),
            $reader,
            [$this->translationAppDir.'/translations'],
            $locales
        );
    }
}
