<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Type;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\FormRendererInterface;

interface FormTypeInterface
{
    function configure(FormBuilder $builder, array $options);

    function buildRenderer(FormRendererInterface $renderer, FormInterface $form);

    function createBuilder(array $options);

    function getDefaultOptions(array $options);

    function getParent(array $options);

    function getName();
}