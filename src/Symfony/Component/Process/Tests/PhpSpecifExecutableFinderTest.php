<?php

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpSpecifExecutableFinder;
use Symfony\Component\Process\Exception\PhpExecutableInvalidVersionException;
use Symfony\Component\Process\Exception\PhpExecutableNotFoundException;
use Symfony\Component\Process\ExecutableFinder;

/**
 * @covers \Symfony\Component\Process\PhpSpecifExecutableFinder
 */
class PhpSpecifExecutableFinderTest extends TestCase
{
    private PhpSpecifExecutableFinder $phpFinder;
    private $executableFinderMock;

    protected function setUp(): void
    {
        // Création d'un mock pour ExecutableFinder
        $this->executableFinderMock = $this->createMock(ExecutableFinder::class);
        $this->phpFinder = new PhpSpecifExecutableFinder($this->executableFinderMock);
    }

    public function testFindReturnsExecutablePath()
    {
        $this->executableFinderMock
            ->method('find')
            ->willReturnCallback(function ($binary) {
                return match ($binary) {
                    'php8.3' => '/bin/php8.3',
                    'php8.4' => '/usr/bin/php8.4',
                    default => null,
                };
            });

        // Test pour PHP 8.3
        $result83 = $this->phpFinder->find('8.3');
        $this->assertStringEndsWith('php8.3', $result83);

        // Test pour PHP 8.4
        $result84 = $this->phpFinder->find('8.4');
        $this->assertStringEndsWith('php8.4', $result84);
    }

    public function testFindThrowsExceptionForInvalidVersion()
    {
        $this->expectException(PhpExecutableInvalidVersionException::class);
        $this->phpFinder->find('invalid-version');
    }

    public function testFindThrowsExceptionWhenExecutableNotFound()
    {
        $this->executableFinderMock
            ->method('find')
            ->willReturn(null);

        $this->expectException(PhpExecutableNotFoundException::class);
        $this->phpFinder->find('8.3');
    }
}
