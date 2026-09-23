<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

final class SymfonyCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCastUuid()
    {
        $uuid = new UuidV4('83a9db35-3c8c-4040-b3c1-02eccc00b419');
        $expectedDump = <<<EODUMP
            Symfony\Component\Uid\UuidV4 {
              #uid: "83a9db35-3c8c-4040-b3c1-02eccc00b419"
              toBase58: "HFzAAuYvev42cCjwqpnKqz"
              toBase32: "43N7DKAF4C810B7G82XK601D0S"
            }
            EODUMP;
        $this->assertDumpEquals($expectedDump, $uuid);

        $uuid = new UuidV6('1ebc50e9-8a23-6704-ad6f-59afd5cda7e5');
        $expectedDump = <<<EODUMP
            Symfony\Component\Uid\UuidV6 {
              #uid: "1ebc50e9-8a23-6704-ad6f-59afd5cda7e5"
              toBase58: "4o8c5m6v4L8h5teww36JDa"
              toBase32: "0YQH8EK2H3CW2ATVTSNZAWV9Z5"
              time: "2021-06-04 08:26:44.591386 UTC"
            }
            EODUMP;

        $this->assertDumpEquals($expectedDump, $uuid);
    }

    public function testCastUlid()
    {
        $ulid = new Ulid('01F7B252SZQGTSQGYSGACASAW6');
        $expectedDump = <<<EODUMP
            Symfony\Component\Uid\Ulid {
              #uid: "01F7B252SZQGTSQGYSGACASAW6"
              toBase58: "1Ba6pJPFWDwghSKFVvfQ1B"
              toRfc4122: "0179d622-8b3f-bc35-9bc3-d98298acab86"
              time: "2021-06-04 08:27:38.687 UTC"
            }
            EODUMP;

        $this->assertDumpEquals($expectedDump, $ulid);
    }

    public function testCastSecurityTokenMasksTheCredentialsItCarries()
    {
        if (!class_exists(UsernamePasswordToken::class)) {
            $this->markTestSkipped('The Security component is not installed.');
        }

        $token = new UsernamePasswordToken(new InMemoryUser('jane', null, ['ROLE_USER']), 'main', ['ROLE_USER']);
        $token->setAttribute('oidc_id_token', 'raw-id-token');
        $token->setAttribute('oidc_access_token', 'raw-access-token');
        $token->setAttribute('oidc_refresh_token', 'raw-refresh-token');
        $token->setAttribute('oidc_access_token_expires_at', 1234567890);
        $token->setAttribute('api_secret', 'raw-secret');
        $token->setAttribute('oidc_acr', 'urn:example:gold');

        // dumped on its own, and as a log context, which is how the profiler sees it
        foreach ([$token, ['token' => $token]] as $var) {
            $dump = $this->getDump($var);

            $this->assertStringNotContainsString('raw-', $dump);
            $this->assertStringContainsString('"oidc_id_token" => "******"', $dump);
            $this->assertStringContainsString('"oidc_access_token" => "******"', $dump);
            $this->assertStringContainsString('"oidc_refresh_token" => "******"', $dump);
            $this->assertStringContainsString('"api_secret" => "******"', $dump);
            $this->assertStringContainsString('"oidc_access_token_expires_at" => 1234567890', $dump);
            $this->assertStringContainsString('"oidc_acr" => "urn:example:gold"', $dump);
        }
    }

    public function testCastPassportMasksTheCredentialsItCarries()
    {
        if (!class_exists(SelfValidatingPassport::class)) {
            $this->markTestSkipped('The Security component is not installed.');
        }

        $passport = new SelfValidatingPassport(new UserBadge('jane', static fn () => null, ['sub' => 'jane']));
        $passport->setAttribute('oidc_token_data', [
            'id_token' => 'raw-id-token',
            'access_token' => 'raw-access-token',
            'refresh_token' => 'raw-refresh-token',
            'token_type' => 'Bearer',
            'expires_in' => 300,
        ]);

        $dump = $this->getDump($passport);

        $this->assertStringNotContainsString('raw-', $dump);
        $this->assertStringContainsString('"id_token" => "******"', $dump);
        $this->assertStringContainsString('"access_token" => "******"', $dump);
        $this->assertStringContainsString('"refresh_token" => "******"', $dump);
        $this->assertStringContainsString('"token_type" => "Bearer"', $dump);
        $this->assertStringContainsString('"expires_in" => 300', $dump);
        $this->assertStringContainsString('"sub" => "jane"', $dump);
    }
}
