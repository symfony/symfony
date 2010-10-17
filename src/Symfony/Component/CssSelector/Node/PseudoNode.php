<?php

namespace Symfony\Component\CssSelector\Node;

use Symfony\Component\CssSelector\SyntaxError;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PseudoNode represents a "selector:ident" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class PseudoNode implements NodeInterface
{
    static protected $unsupported = array(
        'indeterminate', 'first-line', 'first-letter',
        'selection', 'before', 'after', 'link', 'visited',
        'active', 'focus', 'hover',
    );

    protected $element;
    protected $type;
    protected $ident;

    /**
     * @throws SyntaxError When incorrect PseudoNode type is given
     */
    public function __construct($element, $type, $ident)
    {
        $this->element = $element;

        if (!in_array($type, array(':', '::'))) {
            throw new SyntaxError(sprintf('The PseudoNode type can only be : or :: (%s given).', $type));
        }

        $this->type = $type;
        $this->ident = $ident;
    }

    public function __toString()
    {
        return sprintf('%s[%s%s%s]', __CLASS__, $this->element, $this->type, $this->ident);
    }

    /**
     * @throws SyntaxError When unsupported or unknown pseudo-class is found
     */
    public function toXpath()
    {
        $el_xpath = $this->element->toXpath();

        if (in_array($this->ident, self::$unsupported)) {
            throw new SyntaxError(sprintf('The pseudo-class %s is unsupported', $this->ident));
        }
        $method = 'xpath_'.str_replace('-', '_', $this->ident);
        if (!method_exists($this, $method)) {
            throw new SyntaxError(sprintf('The pseudo-class %s is unknown', $this->ident));
        }

        return $this->$method($el_xpath);
    }

    protected function xpath_checked($xpath)
    {
        // FIXME: is this really all the elements?
        $xpath->addCondition("(@selected or @checked) and (name(.) = 'input' or name(.) = 'option')");

        return $xpath;
    }

    /**
     * @throws SyntaxError If this element is the root element
     */
    protected function xpath_root($xpath)
    {
        // if this element is the root element
        throw new SyntaxError();
    }

    protected function xpath_first_child($xpath)
    {
        $xpath->addStarPrefix();
        $xpath->addNameTest();
        $xpath->addCondition('position() = 1');

        return $xpath;
    }

    protected function xpath_last_child($xpath)
    {
        $xpath->addStarPrefix();
        $xpath->addNameTest();
        $xpath->addCondition('position() = last()');

        return $xpath;
    }

    protected function xpath_first_of_type($xpath)
    {
        if ($xpath->getElement() == '*') {
            throw new SyntaxError('*:first-of-type is not implemented');
        }
        $xpath->addStarPrefix();
        $xpath->addCondition('position() = 1');

        return $xpath;
    }

    /**
     * @throws SyntaxError Because *:last-of-type is not implemented
     */
    protected function xpath_last_of_type($xpath)
    {
        if ($xpath->getElement() == '*') {
            throw new SyntaxError('*:last-of-type is not implemented');
        }
        $xpath->addStarPrefix();
        $xpath->addCondition('position() = last()');

        return $xpath;
    }

    protected function xpath_only_child($xpath)
    {
        $xpath->addNameTest();
        $xpath->addStarPrefix();
        $xpath->addCondition('last() = 1');

        return $xpath;
    }

    /**
     * @throws SyntaxError Because *:only-of-type is not implemented
     */
    protected function xpath_only_of_type($xpath)
    {
        if ($xpath->getElement() == '*') {
            throw new SyntaxError('*:only-of-type is not implemented');
        }
        $xpath->addCondition('last() = 1');

        return $xpath;
    }

    protected function xpath_empty($xpath)
    {
        $xpath->addCondition('not(*) and not(normalize-space())');

        return $xpath;
    }
}
