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
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TransNode extends \Twig_Node
{
    public function __construct(\Twig_NodeInterface $body, \Twig_NodeInterface $domain, \Twig_Node_Expression $count = null, \Twig_Node_Expression $vars = null, \Twig_Node_Expression $locale = null, $lineno = 0, $tag = null)
    {
        parent::__construct(array('count' => $count, 'body' => $body, 'domain' => $domain, 'vars' => $vars, 'locale' => $locale), array(), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler $compiler A Twig_Compiler instance
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $vars = $this->getNode('vars');
        $defaults = new \Twig_Node_Expression_Array(array(), -1);
        if ($vars instanceof \Twig_Node_Expression_Array) {
            $defaults = $this->getNode('vars');
            $vars = null;
        }
        list($msg, $defaults) = $this->compileString($this->getNode('body'), $defaults);

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

        if (null !== $vars) {
            $compiler->raw('array_merge(');
            $this->compileDefaults($compiler, $defaults);
            $compiler
                ->raw(', ')
                ->subcompile($this->getNode('vars'))
                ->raw(')')
            ;
        } else {
            $this->compileDefaults($compiler, $defaults);
        }

        $compiler
            ->raw(', ')
            ->subcompile($this->getNode('domain'))
        ;
        if (null !== $this->getNode('locale')) {
            $compiler
                ->raw(', ')
                ->subcompile($this->getNode('locale'))
            ;
        }
        $compiler->raw(");\n");
    }

    protected function compileDefaults(\Twig_Compiler $compiler, \Twig_Node_Expression_Array $defaults)
    {
        $compiler->raw('array(');
        foreach ($defaults as $name => $default) {
            $compiler
                ->repr($name)
                ->raw(' => ')
                ->subcompile($default)
                ->raw(', ')
            ;
        }
        $compiler->raw(')');
    }

    protected function compileString(\Twig_NodeInterface $body, \Twig_Node_Expression_Array $vars)
    {
        if ($body instanceof \Twig_Node_Expression_Constant) {
            $msg = $body->getAttribute('value');
        } elseif ($body instanceof \Twig_Node_Text) {
            $msg = $body->getAttribute('data');
        } else {
            return array($body, $vars);
        }

        $current = array();
        foreach ($vars as $name => $var) {
            $current[$name] = true;
        }

        preg_match_all('/(?<!%)%([^%]+)%/', $msg, $matches);
        foreach ($matches[1] as $var) {
            if (!isset($current['%'.$var.'%'])) {
                $vars->setNode('%'.$var.'%', new \Twig_Node_Expression_Name($var, $body->getLine()));
            }
        }

        return array(new \Twig_Node_Expression_Constant(str_replace('%%', '%', trim($msg)), $body->getLine()), $vars);
    }
}
