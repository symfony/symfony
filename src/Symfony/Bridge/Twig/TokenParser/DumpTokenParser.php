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
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the 'dump' tag.
 *
 * Dump variables with:
 *
 *     {% dump %}
 *     {% dump foo %}
 *     {% dump foo, bar %}
 *
 * @author Julien Galenski <julien.galenski@gmail.com>
 *
 * @final since Symfony 4.4
 */
class DumpTokenParser extends AbstractTokenParser
{
    /**
     * {@inheritdoc}
     *
     * @return Node
     */
    public function parse(Token $token)
    {
        $values = null;
        if (!$this->parser->getStream()->test(Token::BLOCK_END_TYPE)) {
            $values = $this->parser->getExpressionParser()->parseMultitargetExpression();
        }
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new DumpNode($this->parser->getVarName(), $values, $token->getLine(), $this->getTag());
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getTag()
    {
        return 'dump';
    }
}
