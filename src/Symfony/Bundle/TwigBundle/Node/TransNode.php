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
class TransNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $body, \Twig_NodeInterface $domain, \Twig_Node_Expression $count = null, \Twig_Node_Expression $vars = null, $isSimple, $lineno, $tag = null)
    {
        parent::__construct(array('count' => $count, 'body' => $body, 'domain' => $domain, 'vars' => $vars), array('is_simple' => $isSimple), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler A Twig_Compiler instance
     */
    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);

        $defaults = null;
        if ($this->getAttribute('is_simple')) {
            list($msg, $defaults) = $this->compileString($this->getNode('body'));
        } else {
            $msg = $this->getNode('body');
        }

        $method = null === $this->getNode('count') ? 'trans' : 'transChoice';

        $compiler
            ->write('echo $this->env->getExtension(\'translator\')->getTranslator()->'.$method.'(')
            ->subcompile($msg)
        ;

        $compiler->raw(', ');

        if (null !== $this->getNode('count')) {
            $compiler
                ->subcompile($this->getNode('count'))
                ->raw(', ')
            ;
        }

        $compiler->raw('array_merge(');

        if (null === $defaults) {
            $compiler->raw('array()');
        } else {
            $compiler->raw('array(');
            foreach ($defaults as $default) {
                $compiler
                    ->string('{{ '.$default->getAttribute('name').' }}')
                    ->raw(' => ')
                    ->subcompile($default)
                    ->raw(', ')
                ;
            }
            $compiler->raw(')');
        }

        $compiler->raw(', ');

        if (null === $this->getNode('vars')) {
            $compiler->raw('array()');
        } else {
            $compiler->subcompile($this->getNode('vars'));
        }

        $compiler
            ->raw('), ')
            ->subcompile($this->getNode('domain'))
            ->raw(");\n")
        ;
    }

    protected function compileString(\Twig_NodeInterface $body)
    {
        if ($body instanceof \Twig_Node_Expression_Name || $body instanceof \Twig_Node_Expression_Constant) {
            return array($body, array());
        }

        $msg = '';
        $vars = array();
        foreach ($body as $node) {
            if ($node instanceof \Twig_Node_Print) {
                $n = $node->getNode('expr');
                while ($n instanceof \Twig_Node_Expression_Filter) {
                    $n = $n->getNode('node');
                }
                $msg .= sprintf('{{ %s }}', $n->getAttribute('name'));
                $vars[] = new \Twig_Node_Expression_Name($n->getAttribute('name'), $n->getLine());
            } else {
                $msg .= $node->getAttribute('data');
            }
        }

        return array(new \Twig_Node(array(new \Twig_Node_Expression_Constant(trim($msg), $node->getLine()))), $vars);
    }
}
