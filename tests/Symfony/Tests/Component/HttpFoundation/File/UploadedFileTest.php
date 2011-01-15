<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadedFileTest extends \PHPUnit_Framework_TestCase
{
    public function testFileUploadsMustBeEnabled()
    {
        // we can't change this setting without modifying php.ini :(
        if (!ini_get('file_uploads')) {
            $this->setExpectedException('Symfony\Component\HttpFoundation\File\Exception\FileException');

            new UploadedFile(
                __DIR__.'/Fixtures/test.gif',
                'original.gif',
                'image/gif',
                filesize(__DIR__.'/Fixtures/test.gif'),
                UPLOAD_ERR_OK
            );
        }
    }

    public function testErrorIsOkByDefault()
    {
        // we can't change this setting without modifying php.ini :(
        if (ini_get('file_uploads')) {
            $file = new UploadedFile(
                __DIR__.'/Fixtures/test.gif',
                'original.gif',
                'image/gif',
                filesize(__DIR__.'/Fixtures/test.gif'),
                null
            );

            $this->assertEquals(UPLOAD_ERR_OK, $file->getError());
        }
    }
}