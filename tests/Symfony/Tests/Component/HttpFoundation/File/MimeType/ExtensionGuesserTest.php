<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser;

class ExtensionGuesserTest extends \PHPUnit_Framework_TestCase
{
    public function testGuessExtensionWithUnknownMimeType()
    {
        $mimeType = 'unknown/mimetype';

        $this->assertNull(ExtensionGuesser::guess($mimeType));
    }

    public function testGuessExtensionFromMimeType()
    {
        $mimeType = 'image/jpeg';

        $this->assertEquals('jpg', ExtensionGuesser::guess($mimeType));
    }
}
