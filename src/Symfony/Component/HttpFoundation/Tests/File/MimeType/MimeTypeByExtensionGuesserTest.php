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

use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeByExtensionGuesser;
use PHPUnit\Framework\TestCase;

/**
 * Tests if a correct mime type is returned.
 */
class MimeTypeByExtensionGuesserTest extends TestCase
{
    /**
     * @var MimeTypeByExtensionGuesser
     */
    protected $guesser;

    /**
     * @return array
     */
    public function extensionDataProvider()
    {
        return array(
            array('jpg', 'image/jpeg'),
            array('wmz', 'application/x-msmetafile'),
            array('ecelp9600', 'audio/vnd.nuera.ecelp9600'),
            array('fabpot', null),
        );
    }

    public function setUp()
    {
        $this->guesser = new MimeTypeByExtensionGuesser();
    }

    /**
     * @dataProvider extensionDataProvider
     *
     * @param $extension
     * @param $mimeType
     */
    public function testMimeTypeGuessing($extension, $mimeType)
    {
        $result = $this->guesser->guess($extension);
        $this->assertSame($result, $mimeType);
    }
}
