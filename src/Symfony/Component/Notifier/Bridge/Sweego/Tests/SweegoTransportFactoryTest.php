<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego\Tests;

use Symfony\Component\Notifier\Bridge\Sweego\SweegoTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\MissingRequiredOptionTestTrait;

final class SweegoTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use MissingRequiredOptionTestTrait;

    public function createFactory(): SweegoTransportFactory
    {
        return new SweegoTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sweego://host.test?region=REGION&campaign_type=CAMPAIGN_TYPE',
            'sweego://apiKey@host.test?region=REGION&campaign_type=CAMPAIGN_TYPE',
        ];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: region' => ['sweego://apiKey@default?campaign_type=CAMPAIGN_TYPE'];
        yield 'missing option: campaign_type' => ['sweego://apiKey@default?region=REGION'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sweego://apiKey@default'];
        yield [false, 'somethingElse://apiKey@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default'];
    }
}
