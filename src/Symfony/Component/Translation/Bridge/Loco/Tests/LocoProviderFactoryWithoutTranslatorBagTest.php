<?php

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;

class LocoProviderFactoryWithoutTranslatorBagTest extends LocoProviderFactoryTest
{
    public function createFactory(): ProviderFactoryInterface
    {
        return new LocoProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), null);
    }
}
