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
use Symfony\Component\Translation\Test\AbstractProviderFactoryTestCase;
use Symfony\Component\Translation\Test\IncompleteDsnTestTrait;
use Symfony\Component\Translation\TranslatorBagInterface;

class LocoProviderFactoryTest extends AbstractProviderFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public static function supportsProvider(): iterable
    {
        yield [true, 'loco://API_KEY@default'];
        yield [false, 'somethingElse://API_KEY@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://API_KEY@default'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'loco://localise.biz',
            'loco://API_KEY@default',
        ];

        yield [
            'loco://localise.biz?status=translated,provisional',
            'loco://API_KEY@default?status=translated,provisional',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['loco://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new LocoProviderFactory(new MockHttpClient(), new NullLogger(), 'en', $this->createMock(LoaderInterface::class), $this->createMock(TranslatorBagInterface::class));
    }
}
