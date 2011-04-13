<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\TokenParser;

use Symfony\Bridge\Twig\Node\TransNode;

/**
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TransChoiceTokenParser extends TransTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param  \Twig_Token $token A Twig_Token instance
     *
     * @return \Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(\Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $vars = new \Twig_Node_Expression_Array(array(), $lineno);

        $body = null;
        $count = $this->parser->getExpressionParser()->parseExpression();
        $domain = new \Twig_Node_Expression_Constant('messages', $lineno);

        if (!$stream->test(\Twig_Token::BLOCK_END_TYPE) && $stream->test('for')) {
            // {% transchoice count for "message" %}
            // {% transchoice count for message %}
            $stream->next();
            $body = $this->parser->getExpressionParser()->parseExpression();
        }

        if ($stream->test('with')) {
            // {% transchoice count with vars %}
            $stream->next();
            $vars = $this->parser->getExpressionParser()->parseExpression();
        }

        if ($stream->test('from')) {
            // {% transchoice count from "messages" %}
            $stream->next();
            $domain = $this->parser->getExpressionParser()->parseExpression();
        }

        if (null === $body) {
            // {% transchoice count %}message{% endtranschoice %}
            $stream->expect(\Twig_Token::BLOCK_END_TYPE);
            $body = $this->parser->subparse(array($this, 'decideTransChoiceFork'), true);
        }

        if (!$body instanceof \Twig_Node_Text && !$body instanceof \Twig_Node_Expression) {
            throw new \Twig_Error_Syntax(sprintf('A message must be a simple text (line %s)', $lineno), -1);
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new TransNode($body, $domain, $count, $vars, $lineno, $this->getTag());
    }

    public function decideTransChoiceFork($token)
    {
        return $token->test(array('endtranschoice'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'transchoice';
    }
}
