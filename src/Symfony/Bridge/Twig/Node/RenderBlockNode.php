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

use Twig\Compiler;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;

/**
 * Compiles a call to {@link \Symfony\Component\Form\FormRendererInterface::renderBlock()}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RenderBlockNode extends FunctionExpression
{
    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);
        $this->setNode('arguments', new Node(array_merge(
            iterator_to_array($this->getNode('arguments')),
            array('blockName' => new ConstantExpression($this->getAttribute('name'), $this->getNode('arguments')->getTemplateLine()))
        )));

        parent::compile($compiler);
    }
}
