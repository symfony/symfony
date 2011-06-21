<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

require_once __DIR__ . '/TypeTestCase.php';

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileTypeTest extends TypeTestCase
{
    public function testDontPassValueToView()
    {
        $form = $this->factory->create('file');
        $form->bind(array(
            'file' => $this->createUploadedFileMock('abcdef', 'original.jpg', true),
        ));
        $view = $form->createView();

        $this->assertEquals('', $view->get('value'));
    }

    private function createUploadedFileMock($name, $originalName, $valid)
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $file
            ->expects($this->any())
            ->method('getBasename')
            ->will($this->returnValue($name))
        ;
        $file
            ->expects($this->any())
            ->method('getClientOriginalName')
            ->will($this->returnValue($originalName))
        ;
        $file
            ->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue($valid))
        ;

        return $file;
    }
}
