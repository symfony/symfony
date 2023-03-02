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

use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Provider\ProviderFactoryInterface;
use Symfony\Component\Translation\Test\ProviderFactoryTestCase;

class LocoProviderFactoryTest extends ProviderFactoryTestCase
{
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
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['loco://default'];
    }

    public function createFactory(): ProviderFactoryInterface
    {
        return new LocoProviderFactory($this->getClient(), $this->getLogger(), $this->getDefaultLocale(), $this->getLoader(), $this->getTranslatorBag());
    }
}
