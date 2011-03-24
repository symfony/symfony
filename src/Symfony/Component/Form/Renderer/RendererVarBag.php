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

class RendererVarBag extends \ArrayObject
{
    public function offsetGet($name)
    {
        $value = parent::offsetGet($name);

        if (is_callable($value)) {
            $value = $value();
        }

        return $value;
    }
}