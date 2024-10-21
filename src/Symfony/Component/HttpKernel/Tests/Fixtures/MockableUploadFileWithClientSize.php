<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class MockableUploadFileWithClientSize extends UploadedFile
{
    public function getClientSize(): int
    {
        return 0;
    }
}
