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

use Symfony\Component\Mime\FileBinaryMimeTypeGuesser;
use Symfony\Component\Mime\MimeTypeGuesserInterface;

class FileBinaryMimeTypeGuesserTest extends AbstractMimeTypeGuesserTestCase
{
    protected function getGuesser(): MimeTypeGuesserInterface
    {
        return new FileBinaryMimeTypeGuesser();
    }

    public function testGuessWithDuplicatedFileType()
    {
        $this->markTestSkipped('Result varies depending on the OS');
    }
}
