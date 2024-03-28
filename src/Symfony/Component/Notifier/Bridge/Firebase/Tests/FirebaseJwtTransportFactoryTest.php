<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase\Tests;

use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Cesur APAYDIN <https://github.com/cesurapp>
 */
final class FirebaseJwtTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): FirebaseTransportFactory
    {
        return new FirebaseTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'firebase-jwt://fcm.googleapis.com/v1/projects/test_project/messages:send',
            'firebase-jwt://credentials_content:ewogICJ0eXBlIjogIiIsCiAgInByb2plY3RfaWQiOiAidGVzdF9wcm9qZWN0Igp9Cg==@default',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'firebase-jwt://credentials_path:crendentials.json@default'];
        yield [true, 'firebase-jwt://credentials_content:base64Content@default'];
        yield [false, 'somethingElse://username:password@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default'];
    }
}
