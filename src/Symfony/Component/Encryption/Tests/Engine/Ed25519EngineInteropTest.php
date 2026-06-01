<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\Phpseclib\PhpseclibEd25519Engine;
use Symfony\Component\Encryption\Engine\Sodium\SodiumEd25519Engine;

final class Ed25519EngineInteropTest extends TestCase
{
    protected function setUp(): void
    {
        if (!(new SodiumEd25519Engine())->isAvailable() || !(new PhpseclibEd25519Engine())->isAvailable()) {
            self::markTestSkipped('Both sodium and phpseclib Ed25519 engines are required.');
        }
    }

    public function testPhpseclibEngineRoundTrip()
    {
        $engine = new PhpseclibEd25519Engine();
        [$public, $private] = $engine->generateKeyPair();

        $signature = $engine->sign('terms', $private);

        self::assertSame(64, \strlen($signature));
        self::assertTrue($engine->verify($signature, 'terms', $public));
    }

    public function testSodiumSignsPhpseclibVerifies()
    {
        $sodium = new SodiumEd25519Engine();
        $phpseclib = new PhpseclibEd25519Engine();
        [$public, $private] = $sodium->generateKeyPair();

        $signature = $sodium->sign('cross-engine', $private);

        self::assertTrue($phpseclib->verify($signature, 'cross-engine', $public));
    }

    public function testPhpseclibSignsSodiumVerifies()
    {
        $sodium = new SodiumEd25519Engine();
        $phpseclib = new PhpseclibEd25519Engine();
        [$public, $private] = $phpseclib->generateKeyPair();

        $signature = $phpseclib->sign('cross-engine', $private);

        self::assertTrue($sodium->verify($signature, 'cross-engine', $public));
    }

    public function testBothEnginesProduceIdenticalSignatures()
    {
        $sodium = new SodiumEd25519Engine();
        $phpseclib = new PhpseclibEd25519Engine();
        [, $private] = $sodium->generateKeyPair();

        self::assertSame(
            $sodium->sign('deterministic', $private),
            $phpseclib->sign('deterministic', $private),
        );
    }
}
