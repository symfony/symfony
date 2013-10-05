<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Ione Souza Junior <junior@ionixjunior.com.br>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

class PercentTypeTest extends LocalizedTestCase
{
    public function testSubmitCastsToPercent()
    {
        $form = $this->factory->create('percent');

        $form->bind('15.0001');

        $this->assertSame(0.150001, $form->getData());
        $this->assertSame('15', $form->getViewData());
    }

    public function testSubmitCastsToPercentAsIntegerType()
    {
        $form = $this->factory->create('percent', null, array('type' => 'integer'));

        $form->bind('15.0001');

        $this->assertSame(15, $form->getData());
        $this->assertSame('15', $form->getViewData());
    }
}