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
use Twig\Node\Expression\Variable\LocalVariable;
use Twig\Node\Node;
use Twig\Node\Nodes;
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
 */
final class DumpTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $values = null;
        if (!$this->parser->getStream()->test(Token::BLOCK_END_TYPE)) {
            $values = method_exists($this->parser, 'parseExpression') ?
                $this->parseMultitargetExpression() :
                $this->parser->getExpressionParser()->parseMultitargetExpression();
        }
        $this->parser->getStream()->expect(Token::BLOCK_END_TYPE);

        return new DumpNode(class_exists(LocalVariable::class) ? new LocalVariable(null, $token->getLine()) : $this->parser->getVarName(), $values, $token->getLine(), $this->getTag());
    }

    private function parseMultitargetExpression(): Node
    {
        $targets = [];
        while (true) {
            $targets[] = $this->parser->parseExpression();
            if (!$this->parser->getStream()->nextIf(Token::PUNCTUATION_TYPE, ',')) {
                break;
            }
        }

        return new Nodes($targets);
    }

    public function getTag(): string
    {
        return 'dump';
    }
}
