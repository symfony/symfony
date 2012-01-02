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

use Symfony\Component\Form\CallbackTransformer;

require_once __DIR__ . '/TypeTestCase.php';


class TextTypeTest extends TypeTestCase
{
    public function testBindWorksWithDataTransformers()
    {
        $builder = $this->builder->create('text');

        $transformer = new CallbackTransformer(function($value) {

            if (null === $value) {
                return null;
            }

            return implode(',', $value);

        }, function($value) {
            
            if (null === $value) {
                return null;
            }

            return explode(',', $value);

        });

        $builder->appendClientTransformer($transformer);
        $builder->setData(array('1','2','3'));

        $form = $builder->getForm();

        $this->assertSame(array('1','2','3'), $form->getData());
        $this->assertSame('1,2,3', $form->getClientData());

        $form->bind('4,5,6');

        $this->assertSame(array('4','5','6'), $form->getData());
        $this->assertSame('4,5,6', $form->getClientData());
    }
}

