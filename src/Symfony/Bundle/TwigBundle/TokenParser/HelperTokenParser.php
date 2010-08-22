<?php

namespace Symfony\Bundle\TwigBundle\TokenParser;

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
     * @return string The tag name
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
        return $this->output(
            $this->markAsSafe(
                $this->call(
                    $this->getAttribute('_view', $this->helper),
                    $this->method,
                    $this->getNodeValues($values)
                )
            )
        );
    }
}
