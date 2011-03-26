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

use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Renderer\FormRendererFactoryInterface;

class ArrayRendererFactoryLoader implements FormRendererFactoryLoaderInterface
{
    private $factories;

    public function __construct(array $factories)
    {
        foreach ($factories as $factory) {
            if (!$factory instanceof FormRendererFactoryInterface) {
                throw new UnexpectedTypeException($factory, 'Symfony\Component\Form\Renderer\FormRendererFactoryInterface');
            }
        }

        $this->factories = $factories;
    }

    public function getRendererFactory($name)
    {
        if (!isset($this->factories[$name])) {
            throw new FormException(sprintf('No renderer factory exists with name "%s"', $name));
        }

        return $this->factories[$name];
    }

    public function hasRendererFactory($name)
    {
        return isset($this->factories[$name]);
    }
}