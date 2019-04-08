<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Secret\Storage;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Exception\SecretNotFoundException;
use Symfony\Bundle\FrameworkBundle\Secret\Storage\ChainSecretStorage;
use Symfony\Bundle\FrameworkBundle\Secret\Storage\SecretStorageInterface;

class ChainSecretStorageTest extends TestCase
{
    public function testGetSecret()
    {
        $storage1 = $this->getMockBuilder(SecretStorageInterface::class)->getMock();
        $storage1
            ->expects($this->once())
            ->method('getSecret')
            ->with('foo')
            ->willThrowException(new SecretNotFoundException('foo'));
        $storage2 = $this->getMockBuilder(SecretStorageInterface::class)->getMock();
        $storage2
            ->expects($this->once())
            ->method('getSecret')
            ->with('foo')
            ->willReturn('bar');

        $chainStorage = new ChainSecretStorage([$storage1, $storage2]);

        $this->assertEquals('bar', $chainStorage->getSecret('foo'));
    }

    public function testListSecrets()
    {
        $storage1 = $this->getMockBuilder(SecretStorageInterface::class)->getMock();
        $storage1
            ->expects($this->once())
            ->method('listSecrets')
            ->with(true)
            ->willReturn(['foo' => 'bar']);
        $storage2 = $this->getMockBuilder(SecretStorageInterface::class)->getMock();
        $storage2
            ->expects($this->once())
            ->method('listSecrets')
            ->with(true)
            ->willReturn(['baz' => 'qux']);

        $chainStorage = new ChainSecretStorage([$storage1, $storage2]);

        $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], iterator_to_array($chainStorage->listSecrets(true)));
    }
}
