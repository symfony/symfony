<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LazyChoiceTypeExtension extends AbstractTypeExtension
{
    public static $extendedType;

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('choice_loader', new CallbackChoiceLoader(function () {
            return [
                'Lazy A' => 'lazy_a',
                'Lazy B' => 'lazy_b',
            ];
        }));
    }

    /**
     * {@inheritdoc}
     */
    public static function getExtendedTypes(): iterable
    {
        return [self::$extendedType];
    }
}
