<?php

namespace Symfony\Bundle\TwigBundle\Node;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class IfRoleNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $role, \Twig_NodeInterface $object = null, \Twig_NodeInterface $body, $lineno, $tag = null)
    {
        parent::__construct(array('role' => $role, 'object' => $object, 'body' => $body), array(), $lineno, $tag);
    }

    /**
     * {@inheritdoc}
     */
    public function compile($compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('if ($this->env->getExtension(\'security\')->vote(')
            ->subcompile($this->getNode('role'))
        ;

        if (null !== $this->getNode('object')) {
            $compiler
                ->raw(', ')
                ->subcompile($this->getNode('object'))
            ;
        }

        $compiler
            ->write(')) {')
            ->indent()
            ->subcompile($this->getNode('body'))
            ->outdent()
            ->write('}')
        ;
    }
}
