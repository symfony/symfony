<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\Tests;

use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class MicrosoftTeamsTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): MicrosoftTeamsTransportFactory
    {
        return new MicrosoftTeamsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'microsoftteams://host/webhook',
            'microsoftteams://host/webhook',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'microsoftteams://host/webhook'];
        yield [false, 'somethingElse://host/webhook'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://host/webhook'];
    }
}
