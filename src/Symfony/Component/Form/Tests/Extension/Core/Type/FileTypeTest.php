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

class FileTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\FileType';

    /**
     * @group legacy
     */
    public function testLegacyName()
    {
        $form = $this->factory->create('file');

        $this->assertSame('file', $form->getConfig()->getType()->getName());
    }

    // https://github.com/symfony/symfony/pull/5028
    public function testSetData()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)->getForm();
        $data = $this->getMockBuilder('Symfony\Component\HttpFoundation\File\File')
            ->setConstructorArgs(array(__DIR__.'/../../../Fixtures/foo', 'foo'))
            ->getMock();

        $form->setData($data);

        // Ensures the data class is defined to accept File instance
        $this->assertSame($data, $form->getData());
    }

    public function testSubmit()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE)->getForm();
        $data = $this->createUploadedFileMock('abcdef', 'original.jpg', true);

        $form->submit($data);

        $this->assertSame($data, $form->getData());
    }

    public function testSetDataMultiple()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'multiple' => true,
        ))->getForm();

        $data = array(
            $this->createUploadedFileMock('abcdef', 'first.jpg', true),
            $this->createUploadedFileMock('zyxwvu', 'second.jpg', true),
        );

        $form->setData($data);
        $this->assertSame($data, $form->getData());
    }

    public function testSubmitMultiple()
    {
        $form = $this->factory->createBuilder(static::TESTED_TYPE, null, array(
            'multiple' => true,
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
        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit(array(
            'file' => $this->createUploadedFileMock('abcdef', 'original.jpg', true),
        ));

        $this->assertEquals('', $form->createView()->vars['value']);
    }

    public function testPassMultipartFalseToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE)
            ->createView();

        $this->assertTrue($view->vars['multipart']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullWhenMultiple()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'multiple' => true,
        ));
        // submitted data when an input file is uploaded without choosing any file
        $form->submit(array(null));

        $this->assertSame(array(), $form->getData());
        $this->assertSame(array(), $form->getNormData());
        $this->assertSame(array(), $form->getViewData());
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
