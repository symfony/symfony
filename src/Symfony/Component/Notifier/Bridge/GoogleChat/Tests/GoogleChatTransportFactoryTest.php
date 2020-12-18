<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransportFactory;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class GoogleChatTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn()
    {
        $factory = $this->createFactory();

        $transport = $factory->create(Dsn::fromString('googlechat://abcde-fghij:kl_mnopqrstwxyz%3D@chat.googleapis.com/AAAAA_YYYYY'));

        $this->assertSame('googlechat://chat.googleapis.com/AAAAA_YYYYY', (string) $transport);
    }

    public function testCreateWithThreadKeyInDsn()
    {
        $factory = $this->createFactory();

        $transport = $factory->create(Dsn::fromString('googlechat://abcde-fghij:kl_mnopqrstwxyz%3D@chat.googleapis.com/AAAAA_YYYYY?threadKey=abcdefg'));

        $this->assertSame('googlechat://chat.googleapis.com/AAAAA_YYYYY?threadKey=abcdefg', (string) $transport);
    }

    public function testCreateRequiresCredentials()
    {
        $factory = $this->createFactory();

        $this->expectException(IncompleteDsnException::class);

        $factory->create(Dsn::fromString('googlechat://chat.googleapis.com/v1/spaces/AAAAA_YYYYY/messages'));
    }

    public function testSupportsReturnsTrueWithSupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('googlechat://host/path')));
    }

    public function testSupportsReturnsFalseWithUnsupportedScheme()
    {
        $factory = $this->createFactory();

        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/path')));
    }

    public function testUnsupportedSchemeThrowsUnsupportedSchemeException()
    {
        $factory = $this->createFactory();

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create(Dsn::fromString('somethingElse://host/path'));
    }

    private function createFactory(): GoogleChatTransportFactory
    {
        return new GoogleChatTransportFactory();
    }
}
