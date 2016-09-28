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
 */
class TransNode extends \Twig_Node
{
    public function __construct(\Twig_Node $body, \Twig_Node $domain = null, \Twig_Node_Expression $count = null, \Twig_Node_Expression $vars = null, \Twig_Node_Expression $locale = null, $lineno = 0, $tag = null)
    {
        $nodes = array('body' => $body);
        if (null !== $domain) {
            $nodes['domain'] = $domain;
        }
        if (null !== $count) {
            $nodes['count'] = $count;
        }
        if (null !== $vars) {
            $nodes['vars'] = $vars;
        }
        if (null !== $locale) {
            $nodes['locale'] = $locale;
        }

        parent::__construct($nodes, array(), $lineno, $tag);
    }

    /**
     * Compiles the node to PHP.
     *
     * @param \Twig_Compiler $compiler A Twig_Compiler instance
     */
    public function compile(\Twig_Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $defaults = new \Twig_Node_Expression_Array(array(), -1);
        if ($this->hasNode('vars') && ($vars = $this->getNode('vars')) instanceof \Twig_Node_Expression_Array) {
            $defaults = $this->getNode('vars');
            $vars = null;
        }
        list($msg, $defaults) = $this->compileString($this->getNode('body'), $defaults, (bool) $vars);

        $method = !$this->hasNode('count') ? 'trans' : 'transChoice';

        $compiler
            ->write('echo $this->env->getExtension(\'Symfony\Bridge\Twig\Extension\TranslationExtension\')->getTranslator()->'.$method.'(')
            ->subcompile($msg)
        ;

        $compiler->raw(', ');

        if ($this->hasNode('count')) {
            $compiler
                ->subcompile($this->getNode('count'))
                ->raw(', ')
            ;
        }

        if (null !== $vars) {
            $compiler
                ->raw('array_merge(')
                ->subcompile($defaults)
                ->raw(', ')
                ->subcompile($this->getNode('vars'))
                ->raw(')')
            ;
        } else {
            $compiler->subcompile($defaults);
        }

        $compiler->raw(', ');

        if (!$this->hasNode('domain')) {
            $compiler->repr('messages');
        } else {
            $compiler->subcompile($this->getNode('domain'));
        }

        if ($this->hasNode('locale')) {
            $compiler
                ->raw(', ')
                ->subcompile($this->getNode('locale'))
            ;
        }
        $compiler->raw(");\n");
    }

    protected function compileString(\Twig_Node $body, \Twig_Node_Expression_Array $vars, $ignoreStrictCheck = false)
    {
        if ($body instanceof \Twig_Node_Expression_Constant) {
            $msg = $body->getAttribute('value');
        } elseif ($body instanceof \Twig_Node_Text) {
            $msg = $body->getAttribute('data');
        } else {
            return array($body, $vars);
        }

        preg_match_all('/(?<!%)%([^%]+)%/', $msg, $matches);

        foreach ($matches[1] as $var) {
            $key = new \Twig_Node_Expression_Constant('%'.$var.'%', $body->getLine());
            if (!$vars->hasElement($key)) {
                if ('count' === $var && $this->hasNode('count')) {
                    $vars->addElement($this->getNode('count'), $key);
                } else {
                    $varExpr = new \Twig_Node_Expression_Name($var, $body->getLine());
                    $varExpr->setAttribute('ignore_strict_check', $ignoreStrictCheck);
                    $vars->addElement($varExpr, $key);
                }
            }
        }

        return array(new \Twig_Node_Expression_Constant(str_replace('%%', '%', trim($msg)), $body->getLine()), $vars);
    }
}
