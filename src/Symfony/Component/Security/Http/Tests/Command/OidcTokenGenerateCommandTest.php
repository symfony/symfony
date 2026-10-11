<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Command;

use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Security\Http\AccessToken\Oidc\OidcTokenGenerator;
use Symfony\Component\Security\Http\Command\OidcTokenGenerateCommand;

#[RequiresPhpExtension('openssl')]
class OidcTokenGenerateCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        ClockMock::register(OidcTokenGenerateCommand::class);
    }

    protected function tearDown(): void
    {
        ClockMock::withClockMock(false);
    }

    public function testRelativeNotBeforeFollowsTheClock()
    {
        ClockMock::withClockMock(1893456000);

        $claims = $this->generateClaims('-5 minutes', new MockClock('2020-01-01 00:00:00 UTC'));

        $this->assertSame(1577836800, $claims['iat']);
        $this->assertSame(1577836800 + 3600, $claims['exp']);
        $this->assertSame(1577836800 - 300, $claims['nbf']);
    }

    public function testRelativeNotBeforeFollowsClockMockWithoutAClock()
    {
        ClockMock::withClockMock(1577836800);

        $this->assertSame(1577836800 - 300, $this->generateClaims('-5 minutes')['nbf']);
    }

    public function testInvalidNotBefore()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "--not-before" option must be a valid date, "garbage" given.');

        $this->generateClaims('garbage');
    }

    private function generateClaims(string $notBefore, ?ClockInterface $clock = null): array
    {
        $issuers = ['https://www.example.com'];
        $command = new OidcTokenGenerateCommand($clock);
        $command->addGenerator('main', new OidcTokenGenerator(new AlgorithmManager([new ES256()]), self::getJWKSet(), 'Symfony OIDC', $issuers, 'sub', $clock ?? new MockClock()), ['ES256'], $issuers);

        $tester = new CommandTester($command);
        $tester->execute(['user-identifier' => 'john_doe', '--ttl' => '3600', '--not-before' => $notBefore]);
        $tester->assertCommandIsSuccessful();

        return json_decode((new CompactSerializer())->unserialize(trim($tester->getDisplay()))->getPayload(), true);
    }

    private static function getJWKSet(): JWKSet
    {
        return new JWKSet([
            new JWK([
                'kty' => 'EC',
                'crv' => 'P-256',
                'x' => 'FtgMtrsKDboRO-Zo0XC7tDJTATHVmwuf9GK409kkars',
                'y' => 'rWDE0ERU2SfwGYCo1DWWdgFEbZ0MiAXLRBBOzBgs_jY',
                'd' => '4G7bRIiKih0qrFxc0dtvkHUll19tTyctoCR3eIbOrO0',
            ]),
        ]);
    }
}
