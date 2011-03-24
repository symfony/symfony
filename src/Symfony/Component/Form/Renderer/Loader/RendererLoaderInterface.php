<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Loader;

use Symfony\Component\Form\FormInterface;

interface RendererLoaderInterface
{
    function getRenderer($name, FormInterface $form);

    function hasRenderer($name);
}
