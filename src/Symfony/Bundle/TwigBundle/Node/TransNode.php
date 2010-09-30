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
    public function __construct(\Twig_NodeInterface $body, \Twig_NodeInterface $domain, \Twig_Node_Expression $count = null, \Twig_Node_Expression $vars = null, $lineno, $tag = null)
    {
        parent::__construct(array('count' => $count, 'body' => $body, 'domain' => $domain, 'vars' => $vars), array(), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler A Twig_Compiler instance
     */
    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);

        if ($this->isSimpleString($this->body)) {
            list($msg, $vars) = $this->compileString($this->body);
        } else {
            $msg = $this->body;
            $vars = $this->vars;
        }

        $method = null === $this->count ? 'trans' : 'transChoice';

        $compiler
            ->write('echo $this->env->getExtension(\'translator\')->getTranslator()->'.$method.'(')
            ->subcompile($msg)
        ;

        $compiler->raw(', ');

        if (null !== $this->count) {
            $compiler
                ->subcompile($this->count)
                ->raw(', ')
            ;
        }

        $compiler->raw('array(');

        if (is_array($vars)) {
            foreach ($vars as $var) {
                $compiler
                    ->string('{{ '.$var['name'].' }}')
                    ->raw(' => ')
                    ->subcompile($var)
                    ->raw(', ')
                ;
            }
        } elseif (null !== $vars) {
            $compiler->subcompile($vars);
        } else {
            $compiler->raw('array()');
        }

        $compiler
            ->raw("), ")
            ->subcompile($this->domain)
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
                $n = $node->expr;
                while ($n instanceof \Twig_Node_Expression_Filter) {
                    $n = $n->node;
                }
                $msg .= sprintf('{{ %s }}', $n['name']);
                $vars[] = new \Twig_Node_Expression_Name($n['name'], $n->getLine());
            } else {
                $msg .= $node['data'];
            }
        }

        return array(new \Twig_Node(array(new \Twig_Node_Expression_Constant(trim($msg), $node->getLine()))), $vars);
    }

    protected function isSimpleString(\Twig_NodeInterface $body)
    {
        foreach ($body as $i => $node) {
            if (
                $node instanceof \Twig_Node_Text
                ||
                ($node instanceof \Twig_Node_Print && $node->expr instanceof \Twig_Node_Expression_Name)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }
}
