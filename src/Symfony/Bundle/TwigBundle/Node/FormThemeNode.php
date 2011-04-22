<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Node;

/**
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FormThemeNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $form, \Twig_NodeInterface $resources, $lineno, $tag = null)
    {
        parent::__construct(array('form' => $form, 'resources' => $resources), array(), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler A Twig_Compiler instance
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('echo $this->env->getExtension(\'form\')->setTheme(')
            ->subcompile($this->getNode('form'))
            ->raw(', array(')
        ;

        foreach ($this->getNode('resources') as $resource) {
            $compiler
                ->subcompile($resource)
                ->raw(', ')
            ;
        }

        $compiler->raw('));');
    }
}
