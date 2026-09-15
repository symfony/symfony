<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\BoundsType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BoundsTypeValidatorExtension extends AbstractTypeExtension
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Map errors to the lower bound, the one that has to move to make the range valid
            'error_mapping' => ['.' => 'from'],
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [BoundsType::class];
    }
}
