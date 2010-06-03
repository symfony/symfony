<?php

namespace Symfony\Framework\TwigBundle\Node;

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
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HelperNode extends \Twig_Node
{
    public function __construct($name, $method, \Twig_Node $arguments = null, $echo, $lineno, $tag = null)
    {
        parent::__construct(array('arguments' => $arguments), array('name' => $name, 'method' => $method, 'echo' => $echo), $lineno, $tag);
    }

    public function compile($compiler)
    {
        $compiler
            ->addDebugInfo($this)
            ->write('')
        ;

        if ($this['echo']) {
            $compiler->raw('echo ');
        }

        $compiler->raw('$context[\'_view\']->'.$this['name'].'->'.$this['method'].'(');

        if (null !== $this->arguments) {
            $count = count($this->arguments);
            foreach ($this->arguments as $i => $node) {
                $compiler->subcompile($node);

                if ($i !== $count - 1) {
                    $compiler->raw(', ');
                }
            }
        }

        $compiler->raw(");\n");
    }
}
