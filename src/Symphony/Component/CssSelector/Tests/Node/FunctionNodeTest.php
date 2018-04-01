<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\CssSelector\Tests\Node;

use Symphony\Component\CssSelector\Node\ElementNode;
use Symphony\Component\CssSelector\Node\FunctionNode;
use Symphony\Component\CssSelector\Parser\Token;

class FunctionNodeTest extends AbstractNodeTest
{
    public function getToStringConversionTestData()
    {
        return array(
            array(new FunctionNode(new ElementNode(), 'function'), 'Function[Element[*]:function()]'),
            array(new FunctionNode(new ElementNode(), 'function', array(
                new Token(Token::TYPE_IDENTIFIER, 'value', 0),
            )), "Function[Element[*]:function(['value'])]"),
            array(new FunctionNode(new ElementNode(), 'function', array(
                new Token(Token::TYPE_STRING, 'value1', 0),
                new Token(Token::TYPE_NUMBER, 'value2', 0),
            )), "Function[Element[*]:function(['value1', 'value2'])]"),
        );
    }

    public function getSpecificityValueTestData()
    {
        return array(
            array(new FunctionNode(new ElementNode(), 'function'), 10),
            array(new FunctionNode(new ElementNode(), 'function', array(
                new Token(Token::TYPE_IDENTIFIER, 'value', 0),
            )), 10),
            array(new FunctionNode(new ElementNode(), 'function', array(
                new Token(Token::TYPE_STRING, 'value1', 0),
                new Token(Token::TYPE_NUMBER, 'value2', 0),
            )), 10),
        );
    }
}
