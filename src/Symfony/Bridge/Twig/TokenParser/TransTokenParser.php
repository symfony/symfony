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
class TransTokenParser extends \Twig_TokenParser
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

        $body = null;
        $vars = new \Twig_Node_Expression_Array(array(), $lineno);
        $domain = new \Twig_Node_Expression_Constant('messages', $lineno);
        if (!$stream->test(\Twig_Token::BLOCK_END_TYPE)) {
            if (!$stream->test('from') && !$stream->test('with')) {
                // {% trans "message" %}
                // {% trans message %}
                $body = $this->parser->getExpressionParser()->parseExpression();
            }

            if ($stream->test('with')) {
                // {% trans "message" with vars %}
                $stream->next();
                $vars = $this->parser->getExpressionParser()->parseExpression();
            }

            if ($stream->test('from')) {
                // {% trans "message" from "messages" %}
                $stream->next();
                $domain = $this->parser->getExpressionParser()->parseExpression();
            } elseif (!$stream->test(\Twig_Token::BLOCK_END_TYPE)) {
                throw new \Twig_Error_Syntax(sprintf('Unexpected token. Twig was looking for the "from" keyword line %s)', $lineno), -1);
            }
        }

        if (null === $body) {
            // {% trans %}message{% endtrans %}
            $stream->expect(\Twig_Token::BLOCK_END_TYPE);
            $body = $this->parser->subparse(array($this, 'decideTransFork'), true);
        }

        if (!$body instanceof \Twig_Node_Text && !$body instanceof \Twig_Node_Expression) {
            throw new \Twig_Error_Syntax('A message must be a simple text', -1);
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new TransNode($body, $domain, null, $vars, $lineno, $this->getTag());
    }

    public function decideTransFork($token)
    {
        return $token->test(array('endtrans'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'trans';
    }
}
