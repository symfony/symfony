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

interface FormTypeExtensionInterface
{
    function buildForm(FormBuilder $builder, array $options);

    function buildView(FormView $view, FormInterface $form);

    function buildViewBottomUp(FormView $view, FormInterface $form);

    function getDefaultOptions(array $options);

    function getExtendedType();
}