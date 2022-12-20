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

use Symfony\Component\Mime\Exception\RuntimeException;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;

/**
 * @requires extension fileinfo
 */
class MimeTypesTest extends AbstractMimeTypeGuesserTest
{
    protected function getGuesser(): MimeTypeGuesserInterface
    {
        return new MimeTypes();
    }

    public function testUnsupportedGuesser()
    {
        $guesser = $this->getGuesser();
        $guesser->registerGuesser(new class() implements MimeTypeGuesserInterface {
            public function isGuesserSupported(): bool
            {
                return false;
            }

            public function guessMimeType(string $mimeType): ?string
            {
                throw new RuntimeException('Should never be called.');
            }
        });
        self::assertEquals('image/gif', $guesser->guessMimeType(__DIR__.'/Fixtures/mimetypes/test'));
    }

    public function testGetExtensions()
    {
        $mt = new MimeTypes();
        self::assertSame(['mbox'], $mt->getExtensions('application/mbox'));
        self::assertSame(['ai', 'eps', 'ps'], $mt->getExtensions('application/postscript'));
        self::assertContains('svg', $mt->getExtensions('image/svg+xml'));
        self::assertContains('svg', $mt->getExtensions('image/svg'));
        self::assertSame([], $mt->getExtensions('application/whatever-symfony'));
    }

    public function testGetMimeTypes()
    {
        $mt = new MimeTypes();
        self::assertSame(['application/mbox'], $mt->getMimeTypes('mbox'));
        self::assertContains('application/postscript', $mt->getMimeTypes('ai'));
        self::assertContains('application/postscript', $mt->getMimeTypes('ps'));
        self::assertContains('image/svg+xml', $mt->getMimeTypes('svg'));
        self::assertContains('image/svg', $mt->getMimeTypes('svg'));
        self::assertSame([], $mt->getMimeTypes('symfony'));
    }

    public function testCustomMimeTypes()
    {
        $mt = new MimeTypes([
            'text/bar' => ['foo'],
            'text/baz' => ['foo', 'moof'],
        ]);
        self::assertContains('text/bar', $mt->getMimeTypes('foo'));
        self::assertContains('text/baz', $mt->getMimeTypes('foo'));
        self::assertSame(['foo', 'moof'], $mt->getExtensions('text/baz'));
    }

    /**
     * PHP 8 detects .csv files as "application/csv" (or "text/csv", depending
     * on your system) while PHP 7 returns "text/plain".
     *
     * "text/csv" is described by RFC 7111.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7111
     *
     * @requires PHP 8
     */
    public function testCsvExtension()
    {
        $mt = new MimeTypes();

        $mime = $mt->guessMimeType(__DIR__.'/Fixtures/mimetypes/abc.csv');
        self::assertContains($mime, ['application/csv', 'text/csv']);
        self::assertSame(['csv'], $mt->getExtensions($mime));
    }
}
