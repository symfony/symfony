<?php

namespace Symfony\Component\Translation\Bridge\PoEditor\Tests;

use Symfony\Component\Translation\Bridge\PoEditor\PoEditorProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class PoEditorProviderFactoryTest extends ProviderFactoryTestCase
{
    public function supportsProvider(): iterable
    {
        yield [true, 'poeditor://PROJECT_ID:API_KEY@default'];
        yield [false, 'somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://PROJECT_ID:API_KEY@default'];
    }

    public function createProvider(): iterable
    {
        yield [
            'poeditor://api.poeditor.com',
            'poeditor://PROJECT_ID:API_KEY@default',
        ];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield ['poeditor://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new PoEditorProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader());
    }
}
