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
        $factory = new GoogleChatTransportFactory();

        $dsn = 'googlechat://abcde-fghij:kl_mnopqrstwxyz%3D@chat.googleapis.com/AAAAA_YYYYY';
        $transport = $factory->create(Dsn::fromString($dsn));

        $this->assertSame('googlechat://chat.googleapis.com/AAAAA_YYYYY', (string) $transport);
    }

    public function testCreateWithThreadKeyInDsn()
    {
        $factory = new GoogleChatTransportFactory();

        $dsn = 'googlechat://abcde-fghij:kl_mnopqrstwxyz%3D@chat.googleapis.com/AAAAA_YYYYY?threadKey=abcdefg';
        $transport = $factory->create(Dsn::fromString($dsn));

        $this->assertSame('googlechat://chat.googleapis.com/AAAAA_YYYYY?threadKey=abcdefg', (string) $transport);
    }

    public function testCreateRequiresCredentials()
    {
        $this->expectException(IncompleteDsnException::class);
        $factory = new GoogleChatTransportFactory();

        $dsn = 'googlechat://chat.googleapis.com/v1/spaces/AAAAA_YYYYY/messages';
        $factory->create(Dsn::fromString($dsn));
    }

    public function testSupportsGoogleChatScheme()
    {
        $factory = new GoogleChatTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('googlechat://host/path')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/path')));
    }

    public function testNonGoogleChatSchemeThrows()
    {
        $factory = new GoogleChatTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://host/path'));
    }
}
