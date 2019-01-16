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

use Symfony\Bridge\Twig\Node\FormThemeNode;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the 'form_theme' tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FormThemeTokenParser extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @return Node
     */
    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $form = $this->parser->getExpressionParser()->parseExpression();
        $only = false;

        if ($this->parser->getStream()->test(Token::NAME_TYPE, 'with')) {
            $this->parser->getStream()->next();
            $resources = $this->parser->getExpressionParser()->parseExpression();

            if ($this->parser->getStream()->nextIf(Token::NAME_TYPE, 'only')) {
                $only = true;
            }
        } else {
            $resources = new ArrayExpression([], $stream->getCurrent()->getLine());
            do {
                $resources->addElement($this->parser->getExpressionParser()->parseExpression());
            } while (!$stream->test(Token::BLOCK_END_TYPE));
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new FormThemeNode($form, $resources, $lineno, $this->getTag(), $only);
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @return string The tag name
     */
    public function getTag()
    {
        return 'form_theme';
    }
}
