<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BirthdayType extends AbstractType
{
    /**
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'years' => range((int) date('Y') - 120, date('Y')),
            'invalid_message' => 'Please enter a valid birthdate.',
        ]);

        $resolver->setAllowedTypes('years', 'array');
    }

    public function getParent(): ?string
    {
        return DateType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'birthday';
    }
}
