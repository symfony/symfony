<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath;

/**
 * XPath expression translator interface.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class XPathExpr
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $element;

    /**
     * @var string
     */
    private $condition;

    /**
     * @var boolean
     */
    private $starPrefix;

    /**
     * @param string  $path
     * @param string  $element
     * @param string  $condition
     * @param boolean $starPrefix
     */
    public function __construct($path = '', $element = '*', $condition = '', $starPrefix = false)
    {
        $this->path = $path;
        $this->element = $element;
        $this->condition = $condition;
        $this->starPrefix = $starPrefix;
    }

    /**
     * @param $condition
     *
     * @return XPathExpr
     */
    public function addCondition($condition)
    {
        $this->condition = $this->condition ? sprintf('%s and (%s)', $this->condition, $condition) : $condition;

        return $this;
    }

    /**
     * @return XPathExpr
     */
    public function addNameTest()
    {
        if ('*' !== $this->element) {
            $this->addCondition('name()='.GenericTranslator::getXpathLiteral($this->element));
            $this->element = '*';
        }

        return $this;
    }

    /**
     * @param string $starPrefix
     *
     * @return XPathExpr
     */
    public function setStarPrefix($starPrefix)
    {
        $this->starPrefix = (bool) $starPrefix;

        return $this;
    }

    /**
     * @return boolean
     */
    public function hasStarPrefix()
    {
        return $this->starPrefix;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $path = $this->path.$this->element;
        $condition = $this->condition ? '['.$this->condition.']' : '';

        return $path.$condition;
    }
}
