<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

abstract class AbstractMimeTypeGuesserTest extends TestCase
{
    public static function tearDownAfterClass(): void
    {
        $path = __DIR__.'/Fixtures/mimetypes/to_delete';
        if (file_exists($path)) {
            @chmod($path, 0666);
            @unlink($path);
        }
    }

    abstract protected function getGuesser(): MimeTypeGuesserInterface;

    public function testGuessWithLeadingDash()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        $cwd = getcwd();
        chdir(__DIR__.'/Fixtures/mimetypes');
        try {
            self::assertEquals('image/gif', $this->getGuesser()->guessMimeType('-test'));
        } finally {
            chdir($cwd);
        }
    }

    public function testGuessImageWithoutExtension()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        self::assertEquals('image/gif', $this->getGuesser()->guessMimeType(__DIR__.'/Fixtures/mimetypes/test'));
    }

    public function testGuessImageWithDirectory()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        self::expectException(\InvalidArgumentException::class);
        $this->getGuesser()->guessMimeType(__DIR__.'/Fixtures/mimetypes/directory');
    }

    public function testGuessImageWithKnownExtension()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        self::assertEquals('image/gif', $this->getGuesser()->guessMimeType(__DIR__.'/Fixtures/mimetypes/test.gif'));
    }

    public function testGuessFileWithUnknownExtension()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        self::assertEquals('application/octet-stream', $this->getGuesser()->guessMimeType(__DIR__.'/Fixtures/mimetypes/.unknownextension'));
    }

    public function testGuessWithDuplicatedFileType()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        self::assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $this->getGuesser()->guessMimeType(__DIR__.'/Fixtures/test.docx'));
    }

    public function testGuessWithIncorrectPath()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        self::expectException(\InvalidArgumentException::class);
        $this->getGuesser()->guessMimeType(__DIR__.'/Fixtures/mimetypes/not_here');
    }

    public function testGuessWithNonReadablePath()
    {
        if (!$this->getGuesser()->isGuesserSupported()) {
            self::markTestSkipped('Guesser is not supported');
        }

        if ('\\' === \DIRECTORY_SEPARATOR) {
            self::markTestSkipped('Cannot verify chmod operations on Windows');
        }

        if (!getenv('USER') || 'root' === getenv('USER')) {
            self::markTestSkipped('This test will fail if run under superuser');
        }

        $path = __DIR__.'/Fixtures/mimetypes/to_delete';
        touch($path);
        @chmod($path, 0333);

        if (str_ends_with(sprintf('%o', fileperms($path)), '0333')) {
            self::expectException(\InvalidArgumentException::class);
            $this->getGuesser()->guessMimeType($path);
        } else {
            self::markTestSkipped('Cannot verify chmod operations, change of file permissions failed');
        }
    }
}
