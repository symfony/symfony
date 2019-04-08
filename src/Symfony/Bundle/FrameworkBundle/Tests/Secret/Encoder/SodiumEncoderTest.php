<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Secret\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Secret\Encoder\SodiumEncoder;

/**
 * @requires extension sodium
 */
class SodiumEncoderTest extends TestCase
{
    private $keyPath;

    protected function setUp()
    {
        $this->keyPath = tempnam(sys_get_temp_dir(), 'secret');
        unlink($this->keyPath);
    }

    protected function tearDown()
    {
        @unlink($this->keyPath);
    }

    public function testGenerateKey()
    {
        $encoder = new SodiumEncoder($this->keyPath);
        $resources = $encoder->generateKeys();

        $this->assertCount(1, $resources);
        $this->assertEquals($this->keyPath, $resources[0]);
        $this->assertEquals(32, \strlen(file_get_contents($this->keyPath)));
    }

    public function testGenerateCheckOtherKey()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageRegExp('/^A key already exists in/');

        $encoder = new SodiumEncoder($this->keyPath);

        $encoder->generateKeys();
        $encoder->generateKeys();
    }

    public function testGenerateOverride()
    {
        $encoder = new SodiumEncoder($this->keyPath);

        $encoder->generateKeys();
        $firstKey = file_get_contents($this->keyPath);
        $encoder->generateKeys(true);
        $secondKey = file_get_contents($this->keyPath);

        $this->assertNotEquals($firstKey, $secondKey);
    }

    public function testEncryptAndDecrypt()
    {
        $encoder = new SodiumEncoder($this->keyPath);
        $encoder->generateKeys();

        $plain = 'plain';

        $encrypted = $encoder->encrypt($plain);
        $this->assertNotEquals($plain, $encrypted);
        $decrypted = $encoder->decrypt($encrypted);
        $this->assertEquals($plain, $decrypted);
    }
}
