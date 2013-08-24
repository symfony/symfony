<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Node;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @since v2.1.0
 */
class TransDefaultDomainNode extends \Twig_Node
{
    /**
     * @since v2.1.0
     */
    public function __construct(\Twig_Node_Expression $expr, $lineno = 0, $tag = null)
    {
        parent::__construct(array('expr' => $expr), array(), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler $compiler A Twig_Compiler instance
     *
     * @since v2.1.0
     */
    public function compile(\Twig_Compiler $compiler)
    {
        // noop as this node is just a marker for TranslationDefaultDomainNodeVisitor
    }
}
