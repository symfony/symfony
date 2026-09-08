<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\OAuth2\ClientAuthentication;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretBasic;

class ClientSecretBasicTest extends TestCase
{
    public function testSendsTheCredentialsAsHttpBasicAndNotInTheBody()
    {
        $options = (new ClientSecretBasic('test-client-secret'))->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'refresh_token']]);

        $this->assertSame('test-client-id:test-client-secret', $options['auth_basic']);
        $this->assertSame('refresh_token', $options['body']['grant_type']);
        $this->assertArrayNotHasKey('client_secret', $options['body']);
    }

    /**
     * RFC 6749, Section 2.3.1: the client identifier and the secret are each form-urlencoded
     * before being combined, so that a colon, a percent sign or a non-ASCII byte in either
     * of them survives the round trip.
     */
    public function testFormUrlEncodesEachCredential()
    {
        $options = (new ClientSecretBasic('sec:ret% +é'))->authenticate('client:id', 'https://provider.example.com/token', ['body' => []]);

        $this->assertSame('client%3Aid:sec%3Aret%25+%2B%C3%A9', $options['auth_basic']);
    }

    public function testReportsItsMethod()
    {
        $this->assertSame('client_secret_basic', (new ClientSecretBasic('test-client-secret'))->getMethod());
    }

    public function testRejectsAnEmptySecret()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The OAuth2 client secret cannot be empty.');

        new ClientSecretBasic('');
    }
}
