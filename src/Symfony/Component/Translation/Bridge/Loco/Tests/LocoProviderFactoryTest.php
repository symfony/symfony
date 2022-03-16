<?php

namespace Symfony\Component\Translation\Bridge\Loco\Tests;

use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class LocoProviderFactoryTest extends ProviderFactoryTestCase
{
    public function supportsProvider(): iterable
    {
        yield [true, 'loco://API_KEY@default'];
        yield [false, 'somethingElse://API_KEY@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://API_KEY@default'];
    }

    public function createProvider(): iterable
    {
        yield [
            'loco://localise.biz',
            'loco://API_KEY@default',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield ['loco://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new LocoProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), $this->getTranslatorBag());
    }
}
