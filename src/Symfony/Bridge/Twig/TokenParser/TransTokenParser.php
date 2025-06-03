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
final class TransTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $count = null;
        $vars = new ArrayExpression([], $lineno);
        $domain = null;
        $locale = null;
        $parseExpression = method_exists($this->parser, 'parseExpression')
            ? $this->parser->parseExpression(...)
            : $this->parser->getExpressionParser()->parseExpression(...);

        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            if ($stream->test('count')) {
                // {% trans count 5 %}
                $stream->next();
                $count = $parseExpression();
            }

            if ($stream->test('with')) {
                // {% trans with vars %}
                $stream->next();
                $vars = $parseExpression();
            }

            if ($stream->test('from')) {
                // {% trans from "messages" %}
                $stream->next();
                $domain = $parseExpression();
            }

            if ($stream->test('into')) {
                // {% trans into "fr" %}
                $stream->next();
                $locale = $parseExpression();
            } elseif (!$stream->test(Token::BLOCK_END_TYPE)) {
                throw new SyntaxError('Unexpected token. Twig was looking for the "with", "from", or "into" keyword.', $stream->getCurrent()->getLine(), $stream->getSourceContext());
            }
        }

        // {% trans %}message{% endtrans %}
        $stream->expect(Token::BLOCK_END_TYPE);
        $body = $this->parser->subparse($this->decideTransFork(...), true);

        if (!$body instanceof TextNode && !$body instanceof AbstractExpression) {
            throw new SyntaxError('A message inside a trans tag must be a simple text.', $body->getTemplateLine(), $stream->getSourceContext());
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TransNode($body, $domain, $count, $vars, $locale, $lineno, $this->getTag());
    }

    public function decideTransFork(Token $token): bool
    {
        return $token->test(['endtrans']);
    }

    public function getTag(): string
    {
        return 'trans';
    }
}
