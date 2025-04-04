<?php

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpSpecifExecutableFinder;
use Symfony\Component\Process\Exception\PhpSpecifExecutableInvalidVersionException;
use Symfony\Component\Process\Exception\PhpSpecifExecutableNotFoundException;
use Symfony\Component\Process\Process;

class PhpSpecifExecutableFinderTest extends TestCase
{
    private PhpSpecifExecutableFinder $finder;

    protected function setUp(): void
    {
        $this->finder = new PhpSpecifExecutableFinder();
    }

    public function testFindThrowsExceptionForInvalidVersion()
    {
        $this->expectException(PhpSpecifExecutableInvalidVersionException::class);
        $this->expectExceptionMessage("Invalid php version : invalid.version");
        $this->finder->find('invalid.version');
    }

    public function testFindThrowsExceptionWhenPhpNotFound()
    {
        $this->expectException(PhpSpecifExecutableNotFoundException::class);
        $this->expectExceptionMessage("PHP executable not found for the version : 8.3");

        $mockProcess = $this->createMock(Process::class);
        $mockProcess->method('isSuccessful')->willReturn(false);

        $finder = $this->getMockBuilder(PhpSpecifExecutableFinder::class)
            ->onlyMethods(['runProcess'])
            ->getMock();

        $finder->method('runProcess')->willReturn($mockProcess);

        $finder->find('8.3');
    }

    public function testFindReturnsExecutablePath()
    {
        $mockProcess = $this->createMock(Process::class);
        $mockProcess->method('isSuccessful')->willReturn(true);
        $mockProcess->method('getOutput')->willReturn('/usr/bin/php8.3');

        $finder = $this->getMockBuilder(PhpSpecifExecutableFinder::class)
            ->onlyMethods(['runProcess'])
            ->getMock();

        $finder->method('runProcess')->willReturn($mockProcess);

        $result = $finder->find('8.3');
        $this->assertEquals('/usr/bin/php8.3', trim($result));
    }
}
