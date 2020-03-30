<?php

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Uid\Ulid;

class UidTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\UidType';

    public function testSubmitNull($expected = null, $norm = null, $view = '')
    {
        $form = $this->factory->create($this->getTestedType());
        $form->submit(null);

        $this->assertSame($expected, $form->getData());
        $this->assertSame($norm, $form->getNormData());
        $this->assertSame($view, $form->getViewData());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = '01E4BYF64YZ97MDV6RH0HAMN6X', $expectedData = null)
    {
        $emptyData = Ulid::fromString($emptyData);

        $builder = $this->factory->createBuilder($this->getTestedType());

        $form = $builder->setEmptyData($emptyData)->getForm()->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }
}
