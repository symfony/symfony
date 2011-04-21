<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Twig;

use Assetic\Asset\AssetInterface;
use Assetic\Extension\Twig\AsseticNode as BaseAsseticNode;

/**
 * Assetic node.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class AsseticNode extends BaseAsseticNode
{
    protected function compileAssetUrl(\Twig_Compiler $compiler, AssetInterface $asset, $name)
    {
        $compiler
            ->raw('isset($context[\'assetic\'][\'use_controller\']) && $context[\'assetic\'][\'use_controller\'] ? ')
            ->subcompile($this->getPathFunction($name))
            ->raw(' : ')
            ->subcompile($this->getAssetFunction($asset->getTargetUrl()))
        ;
    }

    private function getPathFunction($name)
    {
        return new \Twig_Node_Expression_Function(
            new \Twig_Node_Expression_Name('path', $this->getLine()),
            new \Twig_Node(array(new \Twig_Node_Expression_Constant('_assetic_'.$name, $this->getLine()))),
            $this->getLine()
        );
    }

    private function getAssetFunction($path)
    {
        $arguments = array(new \Twig_Node_Expression_Constant($path, $this->getLine()));

        if ($this->hasAttribute('package')) {
            $arguments[] = new \Twig_Node_Expression_Constant($this->getAttribute('package'), $this->getLine());
        }

        return new \Twig_Node_Expression_Function(
            new \Twig_Node_Expression_Name('asset', $this->getLine()),
            new \Twig_Node($arguments),
            $this->getLine()
        );
    }
}
