<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpFoundation\JsonResponse;

class PasswordPolicyTest extends AbstractWebTestCase
{
    /**
     * @dataProvider providePassword
     */
    public function testLoginFailBecauseThePasswordIsBlacklisted(string $password, string $expectedMessage)
    {
        // Given
        $client = $this->createClient(['test_case' => 'PasswordPolicy', 'root_config' => 'config.yml']);

        // When
        $client->request('POST', '/chk', [], [], ['CONTENT_TYPE' => 'application/json'], '{"user": {"login": "dunglas", "password": "'.$password.'"}}');
        $response = $client->getResponse();

        // Then
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['error' => $expectedMessage], json_decode($response->getContent(), true));
    }

    public static function providePassword(): iterable
    {
        yield ['foo', 'The password does not fulfill the password policy.'];
        yield ['short?', 'The password does not fulfill the password policy.'];
        yield ['Good password?', 'The password does not fulfill the password policy.'];

        // The following password fulfills the password policy, but is not valid.
        yield ['Is it a v4l1d pasw0rd?', 'Invalid credentials.'];
    }
}
