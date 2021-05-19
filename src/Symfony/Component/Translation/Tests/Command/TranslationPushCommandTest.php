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
use Symfony\Component\Translation\Command\TranslationPushCommand;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\Reader\TranslationReader;
use Symfony\Component\Translation\Tests\Command\TranslationProviderTestCase;
use Symfony\Component\Translation\TranslatorBag;

/**

 */
class TranslationPushCommandTest extends TranslationProviderTestCase
{
    protected function setUp(): void
    {
        putenv('COLUMNS=121');
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('COLUMNS');
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

    public function testPushForceMessages()
    {
        $xliffLoader = new XliffFileLoader();
        $filenameEn = $this->createFile([
            'note' => 'NOTE UPDATED',
            'new.foo' => 'newFoo',
        ]);
        $filenameFr = $this->createFile([
            'note' => 'NOTE MISE À JOUR',
            'new.foo' => 'nouveauFoo',
        ], 'fr');
        $locales = ['en', 'fr'];
        $domains = ['messages'];

        $provider = $this->createMock(ProviderInterface::class);

        $localTranslatorBag = new TranslatorBag();
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameEn, 'en'));
        $localTranslatorBag->addCatalogue($xliffLoader->load($filenameFr, 'fr'));

        $provider->expects($this->once())
            ->method('write')
            ->with($localTranslatorBag);

        $provider->expects($this->once())
            ->method('__toString')
            ->willReturn('null://default');

        $tester = $this->createCommandTester($provider, $locales, $domains);

        $tester->execute(['--locales' => ['en', 'fr'], '--domains' => ['messages'], '--force' => true]);

        $this->assertStringContainsString('[OK] All local translations has been sent to "null" (for "en, fr" locale(s), and "messages" domain(s)).', trim($tester->getDisplay()));
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

    private function createCommandTester(ProviderInterface $provider, array $locales = ['en'], array $domains = ['messages']): CommandTester
    {
        $reader = new TranslationReader();
        $reader->addLoader('xlf', new XliffFileLoader());

        $command = new TranslationPushCommand(
            $this->getProviderCollection($provider, $locales, $domains),
            $reader,
            [$this->translationAppDir.'/translations'],
            $locales
        );
        $application = new Application();
        $application->add($command);

        return new CommandTester($application->find('translation:push'));
    }
}
