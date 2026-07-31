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

    public function testReadNoConfiguredLocales()
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

    public function testReadNoConfiguredDomains()
    {
        $innerProvider = $this->createMock(ProviderInterface::class);
        $innerProvider->expects($this->never())->method('read');

        $filteringProvider = new FilteringProvider(
            $innerProvider,
            ['en'],
            ['messages']
        );

        $this->assertCount(0, $filteringProvider->read(['validators'], ['en'])->getCatalogue('en')->getDomains());
    }

    public function testReadNoConfiguredDomainsKeepsConfiguredLocalesOnly()
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
