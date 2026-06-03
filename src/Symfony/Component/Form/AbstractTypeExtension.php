<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractTypeExtension implements FormTypeExtensionInterface
{
    public function configureOptions(OptionsResolver $resolver): void
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
    }
}
