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
        $compiler
            ->write(sprintf("\$this->env->getExtension('stopwatch')->getStopwatch()->start('%s', 'template');\n", $this->getAttribute('name')))
            ->subcompile($this->getNode('body'))
            ->write(sprintf("\$this->env->getExtension('stopwatch')->getStopwatch()->stop('%s');\n", $this->getAttribute('name')))
        ;
    }
}
