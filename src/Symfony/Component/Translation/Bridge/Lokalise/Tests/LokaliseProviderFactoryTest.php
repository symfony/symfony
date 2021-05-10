<?php

namespace Symfony\Component\Translation\Bridge\Lokalise\Tests;

use Symfony\Component\Translation\Bridge\Lokalise\LokaliseProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class LokaliseProviderFactoryTest extends ProviderFactoryTestCase
{
    public function supportsProvider(): iterable
    {
        yield [true, 'lokalise://PROJECT_ID:API_KEY@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public function createProvider(): iterable
    {
        yield [
            'lokalise://api.lokalise.com',
            'lokalise://PROJECT_ID:API_KEY@default',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield ['lokalise://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new LokaliseProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader());
    }
}
