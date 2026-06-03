<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Novu\Tests;

use Symfony\Component\Notifier\Bridge\Novu\NovuTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

class NovuTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): TransportFactoryInterface
    {
        return new NovuTransportFactory();
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'novu://host'];
        yield [false, 'somethingElse://host'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'novu://host.test',
            'novu://token@host.test',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing key' => ['novu://host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host'];
    }
}
