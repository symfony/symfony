<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Loader\LoaderInterface;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;

class LocoProviderFactoryWithoutTranslatorBagTest extends LocoProviderFactoryTest
{
    public function createFactory(): ProviderFactoryInterface
    {
        return new LocoProviderFactory(new MockHttpClient(), new NullLogger(), 'en', $this->createMock(LoaderInterface::class), null);
    }
}
