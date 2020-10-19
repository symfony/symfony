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
 * Token Parser for the 'transchoice' tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 4.2, use the "trans" tag with a "%count%" parameter instead
 *
 * @final since Symfony 4.4
 */
class TransChoiceTokenParser extends AbstractTokenParser
{
    /**
     * {@inheritdoc}
     *
     * @return Node
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        @trigger_error(sprintf('The "transchoice" tag is deprecated since Symfony 4.2, use the "trans" one instead with a "%%count%%" parameter in %s line %d.', $stream->getSourceContext()->getName(), $lineno), \E_USER_DEPRECATED);

        $vars = new ArrayExpression([], $lineno);

        $count = $this->parser->getExpressionParser()->parseExpression();

        $domain = null;
        $locale = null;

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

        if ($stream->test('into')) {
            // {% transchoice count into "fr" %}
            $stream->next();
            $locale = $this->parser->getExpressionParser()->parseExpression();
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        $body = $this->parser->subparse([$this, 'decideTransChoiceFork'], true);

        if (!$body instanceof TextNode && !$body instanceof AbstractExpression) {
            throw new SyntaxError('A message inside a transchoice tag must be a simple text.', $body->getTemplateLine(), $stream->getSourceContext());
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TransNode($body, $domain, $count, $vars, $locale, $lineno, $this->getTag());
    }

    public function decideTransChoiceFork($token)
    {
        return $token->test(['endtranschoice']);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getTag()
    {
        return 'transchoice';
    }
}
