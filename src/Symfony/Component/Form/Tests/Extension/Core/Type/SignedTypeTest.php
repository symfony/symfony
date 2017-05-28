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

class SignedTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    protected $form;

    protected function setUp()
    {
        parent::setUp();

        $this->form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SignedType', null, array(
            'type' => 'Symfony\Component\Form\Extension\Core\Type\HiddenType',
            'signature_secret' => 'ThisIsSecret',
        ));
        $this->form->setData(null);
    }

    public function testSetData()
    {
        $this->form->setData('foobar');

        $this->assertEquals('foobar', $this->form['data']->getData());
        $this->assertEquals(
            'e643b257dce7856e96027a0df2e58fa91d9a3d01517a3010af7adcc212aa286eb1e13ad62c441367c8a55f7970e078d1998dfbeab6ebf1d80990e27cd98cb81c',
            $this->form['signature']->getData()
        );
    }

    public function testSetOptions()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\SignedType', null, array(
            'type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
            'options' => array('label' => 'Global'),
            'signature_secret' => 'ThisIsSecret',
        ));

        $this->assertEquals('Global', $form['data']->getConfig()->getOption('label'));
        $this->assertTrue($form['data']->isRequired());
    }

    public function testSubmitInvalidSign()
    {
        $input = array('data' => 'foo', 'signature' => 'bar');

        $this->form->submit($input);

        $this->assertEquals('foo', $this->form['data']->getViewData());
        $this->assertEquals('bar', $this->form['signature']->getViewData());
        $this->assertFalse($this->form->isSynchronized());
        $this->assertEquals($input, $this->form->getViewData());
        $this->assertNull($this->form->getData());
    }

    public function testSubmitValidSign()
    {
        $input = array(
            'data' => 'foobar',
            'signature' => 'e643b257dce7856e96027a0df2e58fa91d9a3d01517a3010af7adcc212aa286eb1e13ad62c441367c8a55f7970e078d1998dfbeab6ebf1d80990e27cd98cb81c',
        );

        $this->form->submit($input);

        $this->assertEquals('foobar', $this->form['data']->getViewData());
        $this->assertEquals(
            'e643b257dce7856e96027a0df2e58fa91d9a3d01517a3010af7adcc212aa286eb1e13ad62c441367c8a55f7970e078d1998dfbeab6ebf1d80990e27cd98cb81c',
            $this->form['signature']->getViewData()
        );
        $this->assertTrue($this->form->isSynchronized());
        $this->assertEquals($input, $this->form->getViewData());
        $this->assertEquals('foobar', $this->form->getData());
    }
}