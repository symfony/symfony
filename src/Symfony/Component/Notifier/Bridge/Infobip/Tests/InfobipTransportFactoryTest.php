<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Infobip\Tests;

use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class InfobipTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;
    use MissingRequiredOptionTestTrait;

    public function createFactory(): InfobipTransportFactory
    {
        return new InfobipTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'infobip://host.test?from=0611223344',
            'infobip://authtoken@host.test?from=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'infobip://authtoken@default?from=0611223344'];
        yield [false, 'somethingElse://authtoken@default?from=0611223344'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['infobip://authtoken@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://authtoken@default?from=FROM'];
        yield ['somethingElse://authtoken@default']; // missing "from" option
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['infobip://default?from=0611223344'];
    }
}
