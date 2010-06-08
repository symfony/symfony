<?php

namespace Symfony\Framework\TwigBundle\TokenParser;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Wrapper for Symfony helpers.
 *
 * @package    Symfony
 * @subpackage Framework_TwigBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HelperTokenParser extends \Twig_SimpleTokenParser
{
    protected $helper;
    protected $method;
    protected $grammar;
    protected $tag;

    public function __construct($tag = null, $grammar = null, $helper = null, $method = null)
    {
        $this->tag = $tag;
        $this->grammar = $grammar;
        $this->helper = $helper;
        $this->method = $method;
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * Gets the grammar as an object or as a string.
     *
     * @return string|Twig_Grammar A Twig_Grammar instance or a string
     */
    protected function getGrammar()
    {
        return $this->grammar;
    }

    protected function getNode(array $values, $line)
    {
        $helper = new \Twig_Node_Expression_GetAttr(
            new \Twig_Node_Expression_Name('_view', $line),
            new \Twig_Node_Expression_Constant($this->helper, $line),
            new \Twig_Node(),
            \Twig_Node_Expression_GetAttr::TYPE_ANY,
            $line
        );

        $call = new \Twig_Node_Expression_GetAttr(
            $helper,
            new \Twig_Node_Expression_Constant($this->method, $line),
            new \Twig_Node($this->getArguments($values)),
            \Twig_Node_Expression_GetAttr::TYPE_METHOD,
            $line
        );

        $safe = new \Twig_Node_Expression_Filter(
            $call,
            new \Twig_Node(array(new \Twig_Node_Expression_Constant('safe', $line), new \Twig_Node())),
            $line
        );

        return new \Twig_Node_Print($safe, $line);
    }

    protected function getArguments(array $values)
    {
        $arguments = array();
        foreach ($values as $value) {
            if ($value instanceof \Twig_NodeInterface) {
                $arguments[] = $value;
            }
        }

        return $arguments;
    }
}
