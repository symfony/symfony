<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FacebookPage\Tests;

use Symfony\Component\Notifier\Bridge\FacebookPage\FacebookPageTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class FacebookPageTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): FacebookPageTransportFactory
    {
        return new FacebookPageTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'facebook-page://graph.facebook.com?page_id=1895547427139786&api_version=v26.0',
            'facebook-page://token@default?page_id=1895547427139786',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'facebook-page://token@host.test?page_id=1895547427139786'];
        yield [false, 'somethingElse://token@host.test?page_id=1895547427139786'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host.test?page_id=1895547427139786'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['facebook-page://host.test?page_id=1895547427139786'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: page_id' => ['facebook-page://token@host.test'];
    }
}
