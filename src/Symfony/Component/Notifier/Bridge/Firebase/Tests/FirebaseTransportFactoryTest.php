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
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FirebaseTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): FirebaseTransportFactory
    {
        return new FirebaseTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'firebase://fcm.googleapis.com/v1/projects/<PROJECT_ID>/messages:send',
            'firebase://firebase-adminsdk@stag.iam.gserviceaccount.com?project_id=<PROJECT_ID>&private_key_id=<PRIVATE_KEY_ID>&private_key=<PRIVATE_KEY>',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'firebase://client_email?project_id=1'];
        yield [false, 'somethingElse://client_email?project_id=1'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://client_email'];
    }
}
