<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class EsendexTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $transport = $factory->create(Dsn::fromString('esendex://email:password@host.test?accountreference=testAccountreference&from=testFrom'));

        $this->assertSame('esendex://host.test?accountreference=testAccountreference&from=testFrom', (string) $transport);
    }

    public function testCreateWithMissingEmailThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('esendex://:password@host?accountreference=testAccountreference&from=FROM'));
    }

    public function testCreateWithMissingPasswordThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('esendex://email:@host?accountreference=testAccountreference&from=FROM'));
    }

    public function testCreateWithMissingOptionAccountreferenceThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('esendex://email:password@host?from=FROM'));
    }

    public function testCreateWithMissingOptionFromThrowsIncompleteDsnException()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('esendex://email:password@host?accountreference=ACCOUNTREFERENCE'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('esendex://email:password@host?accountreference=ACCOUNTREFERENCE&from=FROM')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://email:password@host?accountreference=ACCOUNTREFERENCE&from=FROM')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://email:password@host?accountreference=REFERENCE&from=FROM'));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeExceptionEvenIfRequiredOptionIsMissing()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);

        // unsupported scheme and missing "from" option
        $factory->create(Dsn::fromString('somethingElse://email:password@host?accountreference=REFERENCE'));
    }

    private function createFactory(): EsendexTransportFactory
    {
        return new EsendexTransportFactory();
    }
}
