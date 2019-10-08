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
        $this->assertEquals('image/gif', $guesser->guessMimeType(__DIR__.'/Fixtures/mimetypes/test'));
    }

    public function testGetExtensions()
    {
        $mt = new MimeTypes();
        $this->assertSame(['mbox'], $mt->getExtensions('application/mbox'));
        $this->assertSame(['ai', 'eps', 'ps'], $mt->getExtensions('application/postscript'));
        $this->assertContains('svg', $mt->getExtensions('image/svg+xml'));
        $this->assertContains('svg', $mt->getExtensions('image/svg'));
        $this->assertSame([], $mt->getExtensions('application/whatever-symfony'));
    }

    public function testGetMimeTypes()
    {
        $mt = new MimeTypes();
        $this->assertSame(['application/mbox'], $mt->getMimeTypes('mbox'));
        $this->assertContains('application/postscript', $mt->getMimeTypes('ai'));
        $this->assertContains('application/postscript', $mt->getMimeTypes('ps'));
        $this->assertContains('image/svg+xml', $mt->getMimeTypes('svg'));
        $this->assertContains('image/svg', $mt->getMimeTypes('svg'));
        $this->assertSame([], $mt->getMimeTypes('symfony'));
    }
}
