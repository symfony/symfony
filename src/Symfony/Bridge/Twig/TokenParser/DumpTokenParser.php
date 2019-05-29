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

use Symfony\Bridge\Twig\Node\DumpNode;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the 'dump' tag.
 *
 * Dump variables with:
 *
 *     {% dump %}
 *     {% dump() %}
 *     {% dump foo %}
 *     {% dump(foo) %}
 *     {% dump foo, bar %}
 *     {% dump(foo, bar) %}
 *
 * @author Julien Galenski <julien.galenski@gmail.com>
 */
class DumpTokenParser extends AbstractTokenParser
{
    /**
     * {@inheritdoc}
     */
    public function parse(Token $token)
    {
        $values = null;

        $stream = $this->parser->getStream();
        if (!$stream->test(Token::BLOCK_END_TYPE)) {
            if ($stream->test(Token::PUNCTUATION_TYPE, '(') && $stream->look()->test(Token::PUNCTUATION_TYPE, ')')) {
                $stream->next();
                $stream->next();
            } else {
                $values = $this->parser->getExpressionParser()->parseMultitargetExpression();
            }
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new DumpNode($this->parser->getVarName(), $values, $token->getLine(), $this->getTag());
    }

    /**
     * {@inheritdoc}
     */
    public function getTag()
    {
        return 'dump';
    }
}
