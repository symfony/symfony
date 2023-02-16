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
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChoiceTypeExtension extends AbstractTypeExtension
{
    public static $extendedType;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('choices', [
            'A' => 'a',
            'B' => 'b',
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [self::$extendedType];
    }
}
