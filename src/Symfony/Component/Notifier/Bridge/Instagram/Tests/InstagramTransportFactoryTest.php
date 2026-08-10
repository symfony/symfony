<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Instagram\Tests;

use Symfony\Component\Notifier\Bridge\Instagram\InstagramTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class InstagramTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): InstagramTransportFactory
    {
        return new InstagramTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'instagram://graph.instagram.com?user_id=17841400000000000&api_version=v22.0',
            'instagram://token@default?user_id=17841400000000000&api_version=v22.0',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'instagram://token@host.test?user_id=17841400000000000'];
        yield [false, 'somethingElse://token@host.test?user_id=17841400000000000'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host.test?user_id=17841400000000000'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['instagram://host.test?user_id=17841400000000000'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: user_id' => ['instagram://token@host.test'];
    }
}
