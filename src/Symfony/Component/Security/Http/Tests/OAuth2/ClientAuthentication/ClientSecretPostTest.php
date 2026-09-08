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
use Symfony\Component\Security\Http\OAuth2\ClientAuthentication\ClientSecretPost;

class ClientSecretPostTest extends TestCase
{
    public function testSendsTheSecretInTheBody()
    {
        $options = (new ClientSecretPost('test-client-secret'))->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => ['grant_type' => 'refresh_token']]);

        $this->assertSame('test-client-secret', $options['body']['client_secret']);
        $this->assertSame('refresh_token', $options['body']['grant_type']);
        $this->assertArrayNotHasKey('auth_basic', $options);
    }

    public function testKeepsTheOptionsItIsGiven()
    {
        $options = (new ClientSecretPost('test-client-secret'))->authenticate('test-client-id', 'https://provider.example.com/token', ['body' => [], 'timeout' => 3]);

        $this->assertSame(3, $options['timeout']);
    }

    public function testReportsItsMethod()
    {
        $this->assertSame('client_secret_post', (new ClientSecretPost('test-client-secret'))->getMethod());
    }

    public function testRejectsAnEmptySecret()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The OAuth2 client secret cannot be empty.');

        new ClientSecretPost('');
    }
}
