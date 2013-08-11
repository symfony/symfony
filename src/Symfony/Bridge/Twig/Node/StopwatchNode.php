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
 * Represents a stopwatch node.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class StopwatchNode extends \Twig_Node
{
    public function __construct($name, $body, $lineno = 0, $tag = null)
    {
        parent::__construct(array('body' => $body), array('name' => $name), $lineno, $tag);
    }
 
    public function compile(\Twig_Compiler $compiler)
    {
        $name = $this->getAttribute('name');
 
        $compiler
            ->write('$this->env->getExtension(\'stopwatch\')->startEvent(\''.$name.'\');')
            ->subcompile($this->getNode('body'))
            ->write('$this->env->getExtension(\'stopwatch\')->stopEvent(\''.$name.'\');')
            ->raw("\n");
    }
}
