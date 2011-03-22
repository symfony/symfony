<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Renderer\Plugin\FormRendererPluginInterface;

interface FormRendererInterface
{
    function setForm(FormInterface $form);

    function setChildren(array $renderers);

    function setVar($name, $value);

    public function getVar($name);

    function addPlugin(FormRendererPluginInterface $plugin);
}