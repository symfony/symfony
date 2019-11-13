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
use Symfony\Component\HttpFoundation\File\MimeType\FileBinaryMimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

/**
 * @requires extension fileinfo
 * @group legacy
 */
class MimeTypeTest extends TestCase
{
    public function testGuessWithLeadingDash()
    {
        $cwd = getcwd();
        chdir(__DIR__.'/../Fixtures');
        try {
            $this->assertEquals('image/gif', MimeTypeGuesser::getInstance()->guess('-test'));
        } finally {
            chdir($cwd);
        }
    }

    public function testGuessImageWithoutExtension()
    {
        $this->assertEquals('image/gif', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/test'));
    }

    public function testGuessImageWithDirectory()
    {
        $this->expectException('Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException');

        MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/directory');
    }

    public function testGuessImageWithFileBinaryMimeTypeGuesser()
    {
        $guesser = MimeTypeGuesser::getInstance();
        $guesser->register(new FileBinaryMimeTypeGuesser());
        $this->assertEquals('image/gif', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/test'));
    }

    public function testGuessImageWithKnownExtension()
    {
        $this->assertEquals('image/gif', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/test.gif'));
    }

    public function testGuessFileWithUnknownExtension()
    {
        $this->assertEquals('application/octet-stream', MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/.unknownextension'));
    }

    public function testGuessWithIncorrectPath()
    {
        $this->expectException('Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException');
        MimeTypeGuesser::getInstance()->guess(__DIR__.'/../Fixtures/not_here');
    }

    public function testGuessWithNonReadablePath()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Can not verify chmod operations on Windows');
        }

        if (!getenv('USER') || 'root' === getenv('USER')) {
            $this->markTestSkipped('This test will fail if run under superuser');
        }

        $path = __DIR__.'/../Fixtures/to_delete';
        touch($path);
        @chmod($path, 0333);

        if ('0333' == substr(sprintf('%o', fileperms($path)), -4)) {
            $this->expectException('Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException');
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
