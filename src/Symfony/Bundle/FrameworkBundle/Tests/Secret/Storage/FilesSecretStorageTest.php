<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Secret\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Secret\Encoder\SodiumEncoder;
use Symfony\Bundle\FrameworkBundle\Secret\Storage\FilesSecretStorage;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @requires extension sodium
 */
class FilesSecretStorageTest extends TestCase
{
    private $workDir;
    private $encoder;

    protected function setUp()
    {
        $this->workDir = tempnam(sys_get_temp_dir(), 'secret');
        $fs = new Filesystem();
        $fs->remove($this->workDir);
        $fs->mkdir($this->workDir);
        $this->encoder = new SodiumEncoder($this->workDir.'/key');
        $this->encoder->generateKeys();
    }

    protected function tearDown()
    {
        (new Filesystem())->remove($this->workDir);
        unset($this->encoder);
    }

    public function testPutAndGetSecrets()
    {
        $storage = new FilesSecretStorage($this->workDir, $this->encoder);

        $secrets = iterator_to_array($storage->listSecrets());
        $this->assertEmpty($secrets);

        $storage->setSecret('foo', 'bar');

        $this->assertEquals('bar', $storage->getSecret('foo'));
    }

    public function testGetThrowsNotFound()
    {
        $this->expectException(\Symfony\Bundle\FrameworkBundle\Exception\SecretNotFoundException::class);

        $storage = new FilesSecretStorage($this->workDir, $this->encoder);

        $storage->getSecret('not-found');
    }

    public function testListSecrets()
    {
        $storage = new FilesSecretStorage($this->workDir, $this->encoder);

        $secrets = iterator_to_array($storage->listSecrets());
        $this->assertEmpty($secrets);

        $storage->setSecret('foo', 'bar');

        $secrets = iterator_to_array($storage->listSecrets());
        $this->assertCount(1, $secrets);
        $this->assertEquals(['foo'], array_keys($secrets));
        $this->assertEquals([null], array_values($secrets));

        $secrets = iterator_to_array($storage->listSecrets(true));
        $this->assertCount(1, $secrets);
        $this->assertEquals(['foo'], array_keys($secrets));
        $this->assertEquals(['bar'], array_values($secrets));
    }
}
