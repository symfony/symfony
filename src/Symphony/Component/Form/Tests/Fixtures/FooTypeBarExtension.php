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

use Symphony\Component\Form\AbstractTypeExtension;
use Symphony\Component\Form\FormBuilderInterface;

class FooTypeBarExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setAttribute('bar', 'x');
    }

    public function getAllowedOptionValues()
    {
        return array(
            'a_or_b' => array('c'),
        );
    }

    public function getExtendedType()
    {
        return __NAMESPACE__.'\FooType';
    }
}
