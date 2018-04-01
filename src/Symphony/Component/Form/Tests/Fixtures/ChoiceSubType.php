<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Fixtures;

use Symphony\Component\Form\AbstractType;
use Symphony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Paráda József <joczy.parada@gmail.com>
 */
class ChoiceSubType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array('expanded' => true));
        $resolver->setNormalizer('choices', function () {
            return array(
                'attr1' => 'Attribute 1',
                'attr2' => 'Attribute 2',
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'Symphony\Component\Form\Extension\Core\Type\ChoiceType';
    }
}
