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
 * CombinedSelectorNode represents a combinator node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CombinedSelectorNode implements NodeInterface
{
    static protected $_method_mapping = array(
        ' ' => 'descendant',
        '>' => 'child',
        '+' => 'direct_adjacent',
        '~' => 'indirect_adjacent',
    );

    protected $selector;
    protected $combinator;
    protected $subselector;

    public function __construct($selector, $combinator, $subselector)
    {
        $this->selector = $selector;
        $this->combinator = $combinator;
        $this->subselector = $subselector;
    }

    public function __toString()
    {
        $comb = $this->combinator == ' ' ? '<followed>' : $this->combinator;

        return sprintf('%s[%s %s %s]', __CLASS__, $this->selector, $comb, $this->subselector);
    }

    /**
     * @throws SyntaxError When unknown combinator is found
     */
    public function toXpath()
    {
        if (!isset(self::$_method_mapping[$this->combinator])) {
            throw new SyntaxError(sprintf('Unknown combinator: %s', $this->combinator));
        }

        $method = '_xpath_'.self::$_method_mapping[$this->combinator];
        $path = $this->selector->toXpath();

        return $this->$method($path, $this->subselector);
    }

    protected function _xpath_descendant($xpath, $sub)
    {
        // when sub is a descendant in any way of xpath
        $xpath->join('/descendant::', $sub->toXpath());

        return $xpath;
    }

    protected function _xpath_child($xpath, $sub)
    {
        // when sub is an immediate child of xpath
        $xpath->join('/', $sub->toXpath());

        return $xpath;
    }

    protected function _xpath_direct_adjacent($xpath, $sub)
    {
        // when sub immediately follows xpath
        $xpath->join('/following-sibling::', $sub->toXpath());
        $xpath->addNameTest();
        $xpath->addCondition('position() = 1');

        return $xpath;
    }

    protected function _xpath_indirect_adjacent($xpath, $sub)
    {
        // when sub comes somewhere after xpath as a sibling
        $xpath->join('/following-sibling::', $sub->toXpath());

        return $xpath;
    }
}
