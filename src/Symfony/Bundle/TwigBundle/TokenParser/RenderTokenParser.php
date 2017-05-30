<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\TokenParser;

use Symfony\Bundle\TwigBundle\Node\RenderNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the render tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.2, to be removed in 3.0.
 */
class RenderTokenParser extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param Token $token
     *
     * @return Node
     */
    public function parse(Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        // options
        if ($this->parser->getStream()->test(Token::PUNCTUATION_TYPE, ',')) {
            $this->parser->getStream()->next();

            $options = $this->parser->getExpressionParser()->parseExpression();
        } else {
            $options = new ArrayExpression(array(), $token->getLine());
        }

        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new RenderNode($expr, $options, $token->getLine(), $this->getTag());
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'render';
    }
}
