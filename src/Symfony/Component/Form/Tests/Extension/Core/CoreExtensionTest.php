<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\Core\DataMapper\AccessorMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryBuilder;

class CoreExtensionTest extends TestCase
{
    public function testTransformationFailuresAreConvertedIntoFormErrors()
    {
        $formFactoryBuilder = new FormFactoryBuilder();
        $formFactory = $formFactoryBuilder->addExtension(new CoreExtension())
            ->getFormFactory();

        $form = $formFactory->createBuilder()
            ->add('foo', 'Symfony\Component\Form\Extension\Core\Type\DateType')
            ->getForm();
        $form->submit('foo');

        $this->assertFalse($form->isValid());
    }

    public function testMapperExtensionIsLoaded()
    {
        $formFactoryBuilder = new FormFactoryBuilder();
        $formFactory = $formFactoryBuilder->addExtension(new CoreExtension())
            ->getFormFactory();

        $mock = $this->getMockBuilder(stdClass::class)->addMethods(['get', 'set'])->getMock();
        $mock->expects($this->once())->method('get')->willReturn('foo');
        $mock->expects($this->once())->method('set')->with('bar');

        $formBuilder = $formFactory->createBuilder();
        $form = $formBuilder
            ->add(
                'foo',
                TextType::class
            )
            ->setDataMapper(new AccessorMapper(
                function (MockObject $data) { return $data->get(); },
                function (MockObject $data, $value) { return $data->set($value); },
                $formBuilder->getDataMapper()
            ))
            ->setData($mock)
            ->getForm();

        $this->assertInstanceOf(AccessorMapper::class, $form->getConfig()->getDataMapper());
        $form->submit(['foo' => 'bar']);
    }
}
