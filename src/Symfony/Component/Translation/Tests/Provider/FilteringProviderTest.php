<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Provider\FilteringProvider;
use Symfony\Component\Translation\Provider\ProviderInterface;
use Symfony\Component\Translation\TranslatorBag;

class FilteringProviderTest extends TestCase
{
    public function testReadDelegatesWithFilteredLocales()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $expectedBag = new TranslatorBag();
        $innerProvider->expects($this->once())
            ->method('read')
            ->with(['messages'], ['en', 'fr'])
            ->willReturn($expectedBag);

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en', 'fr', null, ''],
            ['messages', 'validators']
        );

        $result = $filteringProvider->read(['messages', 'custom'], ['', null, 'en', 'fr']);

        $this->assertSame($expectedBag, $result);
    }

    public function testReadKeepDomainKeys()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('read')
            ->with(['foo' => 'bar'], ['en'])
            ->willReturn(new TranslatorBag());

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en'],
            ['foo' => 'bar', 'baz' => 'qux']
        );

        $filteringProvider->read(['bar'], ['en']);
    }

    public function testReadNoLocalesWithoutConfiguredLocales()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('read')
            ->with(['messages'], [])
            ->willReturn(new TranslatorBag());

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            [],
            ['messages']
        );

        $filteringProvider->read(['messages'], []);
    }

    public function testReadNoLocalesFallsBackToConfiguredLocales()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('read')
            ->with(['messages'], ['en', 'fr'])
            ->willReturn(new TranslatorBag());

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en', 'fr'],
            ['messages']
        );

        $filteringProvider->read(['messages'], []);
    }

    public function testReadNoMatchingLocales()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->never())->method('read');

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en'],
            ['messages']
        );

        $this->assertCount(0, $filteringProvider->read(['messages'], ['fr'])->getCatalogues());
    }

    public function testReadNoDomainsWithoutConfiguredDomains()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('read')
            ->with([], ['en'])
            ->willReturn(new TranslatorBag());

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en'],
            []
        );

        $filteringProvider->read([], ['en']);
    }

    public function testReadNoDomainsFallsBackToConfiguredDomains()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('read')
            ->with(['messages', 'validators'], ['en'])
            ->willReturn(new TranslatorBag());

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en'],
            ['messages', 'validators']
        );

        $filteringProvider->read([], ['en']);
    }

    public function testReadNoMatchingDomains()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->never())->method('read');

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en'],
            ['messages']
        );

        $translatorBag = $filteringProvider->read(['validators'], ['en']);

        $this->assertCount(1, $translatorBag->getCatalogues());
        $this->assertCount(0, $translatorBag->getCatalogue('en')->getDomains());
    }

    public function testReadRequestedLocalesWithoutConfiguredLocales()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('read')
            ->with(['messages'], ['fr'])
            ->willReturn(new TranslatorBag());

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            [],
            ['messages']
        );

        $filteringProvider->read(['messages'], ['fr']);
    }

    public function testReadKeepsRequestedLocalesThatAreConfigured()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->once())
            ->method('read')
            ->with(['messages'], ['fr'])
            ->willReturn(new TranslatorBag());

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['fr', 'en'],
            ['messages']
        );

        $filteringProvider->read(['messages'], ['fr', 'de']);
    }

    public function testReadNoMatchingDomainsKeepsMatchingLocalesOnly()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->never())->method('read');

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['fr', 'en'],
            ['messages']
        );

        $translatorBag = $filteringProvider->read(['validators'], ['fr', 'de']);

        $this->assertSame(
            ['fr'],
            array_map(static fn (MessageCatalogue $catalogue) => $catalogue->getLocale(), $translatorBag->getCatalogues()),
        );
    }
}
