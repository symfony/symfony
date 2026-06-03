<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AmazonSns\Tests;

use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;

class AmazonSnsTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    public function createFactory(): AmazonSnsTransportFactory
    {
        // Tests will fail if a ~/.aws/config file exists with a default.region value,
        // or if AWS_REGION env variable is set.
        // Setting a profile & region names will bypass default options retrieved by \AsyncAws\Core::get
        $_ENV['AWS_PROFILE'] = 'not-existing';
        $_ENV['AWS_REGION'] = 'us-east-1';

        return new AmazonSnsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['sns://host.test?region=us-east-1', 'sns://host.test'];
        yield ['sns://host.test?region=us-east-1', 'sns://accessId:accessKey@host.test'];
        yield ['sns://host.test?region=eu-west-3', 'sns://host.test?region=eu-west-3'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sns://default'];
        yield [false, 'somethingElse://default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://default'];
    }
}
