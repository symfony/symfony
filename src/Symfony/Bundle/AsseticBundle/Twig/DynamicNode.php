<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Twig;

use Assetic\Extension\Twig\Node;

/**
 * The "dynamic" node uses a controller to render assets.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class DynamicNode extends Node
{
    /**
     * Renders the asset URL using Symfony's path() function.
     */
    protected function getAssetUrlNode(\Twig_NodeInterface $body)
    {
        return new \Twig_Node_Expression_Function(
            new \Twig_Node_Expression_Name('path', $body->getLine()),
            new \Twig_Node(array(
                new \Twig_Node_Expression_Constant('assetic_'.$this->getAttribute('asset_name'), $body->getLine()),
            )),
            $body->getLine()
        );
    }
}
