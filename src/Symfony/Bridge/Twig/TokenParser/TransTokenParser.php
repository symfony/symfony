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
use Twig\Error\SyntaxError;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Node;
use Twig\Node\TextNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the 'trans' tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TransTokenParser extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Token $token
     *
     * @return Node
     *
     * @throws SyntaxError
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $vars = new ArrayExpression(array(), $lineno);
        $domain = null;
        $locale = null;
        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            if ($stream->test('with')) {
                // {% trans with vars %}
                $stream->next();
                $vars = $this->parser->getExpressionParser()->parseExpression();
            }

            if ($stream->test('from')) {
                // {% trans from "messages" %}
                $stream->next();
                $domain = $this->parser->getExpressionParser()->parseExpression();
            }

            if ($stream->test('into')) {
                // {% trans into "fr" %}
                $stream->next();
                $locale = $this->parser->getExpressionParser()->parseExpression();
            } elseif (!$stream->test(Token::BLOCK_END_TYPE)) {
                throw new SyntaxError('Unexpected token. Twig was looking for the "with", "from", or "into" keyword.', $stream->getCurrent()->getLine(), $stream->getSourceContext()->getName());
            }
        }

        // {% trans %}message{% endtrans %}
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse(array($this, 'decideTransFork'), true);

        if (!$body instanceof TextNode && !$body instanceof AbstractExpression) {
            throw new SyntaxError('A message inside a trans tag must be a simple text.', $body->getTemplateLine(), $stream->getSourceContext()->getName());
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TransNode($body, $domain, null, $vars, $locale, $lineno, $this->getTag());
    }

    public function decideTransFork($token)
    {
        return $token->test(array('endtrans'));
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'trans';
    }
}
