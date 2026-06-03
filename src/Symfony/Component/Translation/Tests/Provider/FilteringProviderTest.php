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
}
