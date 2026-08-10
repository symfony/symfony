<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Threads\Tests;

use Symfony\Component\Notifier\Bridge\Threads\ThreadsTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ThreadsTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): ThreadsTransportFactory
    {
        return new ThreadsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'threads://graph.threads.net?user_id=1234567890&api_version=v1.0',
            'threads://token@default?user_id=1234567890',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'threads://token@host.test?user_id=1234567890'];
        yield [false, 'somethingElse://token@host.test?user_id=1234567890'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host.test?user_id=1234567890'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['threads://host.test?user_id=1234567890'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: user_id' => ['threads://token@host.test'];
    }
}
