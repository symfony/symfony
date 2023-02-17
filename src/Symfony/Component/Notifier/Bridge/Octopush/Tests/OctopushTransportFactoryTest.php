<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Octopush\Tests;

use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class OctopushTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): OctopushTransportFactory
    {
        return new OctopushTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'octopush://host.test?from=Heyliot&type=FR',
            'octopush://userLogin:apiKey@host.test?from=Heyliot&type=FR',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'octopush://userLogin:apiKey@default?from=Heyliot&type=FR'];
        yield [false, 'somethingElse://userLogin:apiKet@default?from=Heyliot&type=FR'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['octopush://userLogin:apiKey@default?type=FR'];
        yield 'missing option: type' => ['octopush://userLogin:apiKey@default?from=Heyliot'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://userLogin:apiKey@default?from=0611223344'];
        yield ['somethingElse://userLogin:apiKey@default']; // missing "from" option
    }
}
