<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests\TokenGenerator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UriSafeTokenGeneratorTest extends TestCase
{
    private const ENTROPY = 1000;

    /**
     * A non alpha-numeric byte string.
     */
    private static string $bytes;

    private UriSafeTokenGenerator $generator;

    public static function setUpBeforeClass(): void
    {
        self::$bytes = base64_decode('aMf+Tct/RLn2WQ==');
    }

    protected function setUp(): void
    {
        $this->generator = new UriSafeTokenGenerator(self::ENTROPY);
    }

    public function testGenerateToken()
    {
        $token = $this->generator->generateToken();

        $this->assertTrue(ctype_print($token), 'is printable');
        $this->assertStringNotMatchesFormat('%S+%S', $token, 'is URI safe');
        $this->assertStringNotMatchesFormat('%S/%S', $token, 'is URI safe');
        $this->assertStringNotMatchesFormat('%S=%S', $token, 'is URI safe');
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testValidLength(int $entropy, int $length)
    {
        $generator = new UriSafeTokenGenerator($entropy);
        $token = $generator->generateToken();
        $this->assertSame($length, \strlen($token));
    }

    public static function validDataProvider(): \Iterator
    {
        yield [24, 4];
        yield 'Float length' => [20, 3];
    }

    public function testInvalidLength()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entropy should be greater than 7.');

        new UriSafeTokenGenerator(7);
    }
}
