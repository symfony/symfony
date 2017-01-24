<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\FileValidator;

class FileValidatorPsr7Test extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new FileValidator();
    }

    public function testValidUploadedFile()
    {
        $file = $this->getMock('Psr\Http\Message\UploadedFileInterface');
        $file->expects($this->any())->method('getError')->willReturn(UPLOAD_ERR_OK);

        $this->validator->validate($file, new File());

        $this->assertNoViolation();
    }

    public function testInvalidUploadedFile()
    {
        $file = $this->getMock('Psr\Http\Message\UploadedFileInterface');
        $file->expects($this->any())->method('getError')->willReturn(UPLOAD_ERR_FORM_SIZE);

        $constraint = new File(array(
            'uploadFormSizeErrorMessage' => 'myMessage',
        ));

        $this->validator->validate($file, $constraint);

        $this->buildViolation('myMessage')
            ->setCode(UPLOAD_ERR_FORM_SIZE)
            ->assertRaised();
    }
}
