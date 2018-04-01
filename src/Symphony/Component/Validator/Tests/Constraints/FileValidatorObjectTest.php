<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Constraints;

use Symphony\Component\HttpFoundation\File\File;

class FileValidatorObjectTest extends FileValidatorTest
{
    protected function getFile($filename)
    {
        return new File($filename);
    }
}
