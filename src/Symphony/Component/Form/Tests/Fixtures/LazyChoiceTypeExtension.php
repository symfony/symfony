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
use Symphony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symphony\Component\Form\Extension\Core\Type\ChoiceType;
use Symphony\Component\OptionsResolver\OptionsResolver;

class LazyChoiceTypeExtension extends AbstractTypeExtension
{
    private $extendedType;

    public function __construct($extendedType = ChoiceType::class)
    {
        $this->extendedType = $extendedType;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('choice_loader', new CallbackChoiceLoader(function () {
            return array(
                'Lazy A' => 'lazy_a',
                'Lazy B' => 'lazy_b',
            );
        }));
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return $this->extendedType;
    }
}
