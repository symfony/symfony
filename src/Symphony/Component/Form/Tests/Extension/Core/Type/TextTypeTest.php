<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\Type;

class TextTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symphony\Component\Form\Extension\Core\Type\TextType';

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullReturnsNullWithEmptyDataAsString()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'name', array(
            'empty_data' => '',
        ));

        $form->submit(null);
        $this->assertSame('', $form->getData());
        $this->assertSame('', $form->getNormData());
        $this->assertSame('', $form->getViewData());
    }

    public function provideZeros()
    {
        return array(
            array(0, '0'),
            array('0', '0'),
            array('00000', '00000'),
        );
    }

    /**
     * @dataProvider provideZeros
     *
     * @see https://github.com/symphony/symphony/issues/1986
     */
    public function testSetDataThroughParamsWithZero($data, $dataAsString)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'data' => $data,
        ));
        $view = $form->createView();

        $this->assertFalse($form->isEmpty());

        $this->assertSame($dataAsString, $view->vars['value']);
        $this->assertSame($dataAsString, $form->getData());
    }
}
