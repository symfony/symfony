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

use Symfony\Bundle\TwigBundle\Node\IncludeNode;

/**
 *
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IncludeTokenParser extends \Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param \Twig_Token $token A \Twig_Token instance
     *
     * @return \Twig_NodeInterface A \Twig_NodeInterface instance
     */
    public function parse(\Twig_Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        $variables = null;
        if ($this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'with')) {
            $this->parser->getStream()->next();

            $variables = $this->parser->getExpressionParser()->parseExpression();
        }

        $only = false;
        if ($this->parser->getStream()->test(\Twig_Token::NAME_TYPE, 'only')) {
            $this->parser->getStream()->next();

            $only = true;
        }

        $this->parser->getStream()->expect(\Twig_Token::BLOCK_END_TYPE);

        return new IncludeNode($expr, $variables, $only, $token->getLine(), $this->getTag());
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'include';
    }
}
