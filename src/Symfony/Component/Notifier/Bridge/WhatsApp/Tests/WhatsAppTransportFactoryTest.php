<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\WhatsApp\Tests;

use Symfony\Component\Notifier\Bridge\WhatsApp\WhatsAppTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

/**
 * @author Piero Recchia <piero.recchia@gmail.com>
 */
final class WhatsAppTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): WhatsAppTransportFactory
    {
        return new WhatsAppTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'whatsapp://graph.facebook.com?phone_number_id=123456789&api_version=v26.0',
            'whatsapp://token@default?phone_number_id=123456789',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'whatsapp://token@host.test?phone_number_id=123456789'];
        yield [false, 'somethingElse://token@default?phone_number_id=123456789'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['whatsapp://host.test?phone_number_id=123456789'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: phone_number_id' => ['whatsapp://token@host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:apiKey@default'];
    }
}
