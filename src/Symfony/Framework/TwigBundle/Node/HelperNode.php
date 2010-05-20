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
class HelperNode extends \Twig_Node implements \Twig_NodeListInterface
{
    protected $name;
    protected $method;
    protected $arguments;
    protected $echo;

    public function __construct($name, $method, \Twig_NodeList $arguments, $echo, $lineno, $tag = null)
    {
        parent::__construct($lineno, $tag);
        $this->name = $name;
        $this->method = $method;
        $this->arguments = $arguments;
        $this->echo = $echo;
    }

    public function __toString()
    {
        return get_class($this).'('.$this->arguments.')';
    }

    public function getNodes()
    {
        return array($this->arguments);
    }

    public function setNodes(array $nodes)
    {
        $this->arguments = $nodes[0];
    }

    public function compile($compiler)
    {
        $compiler->addDebugInfo($this);

        if ($this->echo) {
            $compiler->raw('echo ');
        }

        $compiler->write('$context[\'_view\']->'.$this->name.'->'.$this->method.'(');

        $count = count($this->arguments->getNodes());
        foreach ($this->arguments->getNodes() as $i => $node) {
            $compiler->subcompile($node);

            if ($i !== $count - 1) {
                $compiler->raw(', ');
            }
        }

        $compiler->raw(");\n");
    }

    public function getName()
    {
        return $this->name;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getArguments()
    {
        return $this->arguments;
    }
}
