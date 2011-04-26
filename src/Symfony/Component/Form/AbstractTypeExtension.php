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

abstract class AbstractTypeExtension implements FormTypeExtensionInterface
{
    public function buildForm(FormBuilder $builder, array $options)
    {
    }

    public function buildView(FormView $view, FormInterface $form)
    {
    }

    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
    }

    public function getDefaultOptions(array $options)
    {
        return array();
    }
}
