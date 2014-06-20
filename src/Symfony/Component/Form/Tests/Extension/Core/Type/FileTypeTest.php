<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

class FileTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    // https://github.com/symfony/symfony/pull/5028
    public function testSetData()
    {
        $form = $this->factory->createBuilder('file')->getForm();
        $data = $this->createUploadedFileMock('abcdef', 'original.jpg', true);

        $form->setData($data);

        $this->assertSame($data, $form->getData());
    }

    public function testSubmit()
    {
        $form = $this->factory->createBuilder('file')->getForm();
        $data = $this->createUploadedFileMock('abcdef', 'original.jpg', true);

        $form->submit($data);

        $this->assertSame($data, $form->getData());
    }

    // https://github.com/symfony/symfony/issues/6134
    public function testSubmitEmpty()
    {
        $form = $this->factory->createBuilder('file')->getForm();

        $form->submit(null);

        $this->assertNull($form->getData());
    }

    public function testSubmitMultiple()
    {
        $form = $this->factory->createBuilder('file', null, array(
            'multiple' => true
        ))->getForm();

        $data = array(
            $this->createUploadedFileMock('abcdef', 'first.jpg', true),
            $this->createUploadedFileMock('zyxwvu', 'second.jpg', true),
        );

        $form->submit($data);
        $this->assertSame($data, $form->getData());

        $view = $form->createView();
        $this->assertSame('file[]', $view->vars['full_name']);
        $this->assertArrayHasKey('multiple', $view->vars['attr']);
    }

    public function testDontPassValueToView()
    {
        $form = $this->factory->create('file');
        $form->submit(array(
            'file' => $this->createUploadedFileMock('abcdef', 'original.jpg', true),
        ));
        $view = $form->createView();

        $this->assertEquals('', $view->vars['value']);
    }

    private function createUploadedFileMock($name, $originalName, $valid)
    {
        $file = $this
            ->getMockBuilder('Symfony\Component\HttpFoundation\File\UploadedFile')
            ->setConstructorArgs(array(__DIR__.'/../../../Fixtures/foo', 'foo'))
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
