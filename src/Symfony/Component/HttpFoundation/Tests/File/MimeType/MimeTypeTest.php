<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\File\MimeType;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\MimeType\FileBinaryMimeTypeGuesser;

/**
 * @requires extension fileinfo
 */
class MimeTypeTest extends TestCase
{
    protected $path;

    public function testGuessImageWithoutExtension(): void
    {
        $this->assertEquals('image/gif', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/test'));
    }

    public function testGuessImageWithDirectory(): void
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException');

        MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/directory');
    }

    public function testGuessImageWithFileBinaryMimeTypeGuesser(): void
    {
        $guesser = MimeTypeGuesser::getInstance();
        $guesser->register(new FileBinaryMimeTypeGuesser());
        $this->assertEquals('image/gif', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/test'));
    }

    public function testGuessImageWithKnownExtension(): void
    {
        $this->assertEquals('image/gif', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/test.gif'));
    }

    public function testGuessFileWithUnknownExtension(): void
    {
        $this->assertEquals('application/octet-stream', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/.unknownextension'));
    }

    public function testGuessWithIncorrectPath(): void
    {
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException');
        MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/not_here');
    }

    public function testGuessWithNonReadablePath(): void
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Can not verify chmod operations on Windows');
        }

        if (!getenv('USER') || 'root' === getenv('USER')) {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        $path = __DIR__.'/../Fixtures/to_delete';
        touch($path);
        @chmod($path, 0333);

        if ('0333' == substr(sprintf('%o', fileperms($path)), -4)) {
            $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException');
            MimeTypeGuesser::getInstance()->guess($path);
        } else {
            $this->markTestSkipped('Can not verify chmod operations, change of file permissions failed');
        }
    }

    public static function tearDownAfterClass(): void
    {
        $path = __DIR__.'/../Fixtures/to_delete';
        if (file_exists($path)) {
            @chmod($path, 0666);
            @unlink($path);
        }
    }
}
