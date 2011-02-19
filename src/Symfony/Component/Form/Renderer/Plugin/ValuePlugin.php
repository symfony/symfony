<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license infieldation, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Plugin;

use Symfony\Component\Form\Renderer\RendererInterface;

class ValuePlugin implements PluginInterface
{
    private $value;

    public function __construct($value = null)
    {
        $this->value = $value;
    }

    public function setUp(RendererInterface $renderer)
    {
        if (null !== $this->value) {
            $renderer->setParameter('value', $this->value);
        }
    }
}