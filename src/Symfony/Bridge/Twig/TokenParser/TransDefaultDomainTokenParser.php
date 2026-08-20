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

use Symfony\Bridge\Twig\Node\TransDefaultDomainNode;
use Symfony\Bridge\Twig\NodeVisitor\TransDefaultDomainRegistry;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the 'trans_default_domain' tag.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TransDefaultDomainTokenParser extends AbstractTokenParser
{
    public function __construct(
        private readonly TransDefaultDomainRegistry $registry = new TransDefaultDomainRegistry(),
    ) {
    }

    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $expr = $this->parser->parseExpression();
        $source = $stream->getSourceContext();
        $forSelf = false;

        if ($stream->nextIf(Token::NAME_TYPE, 'for')) {
            $stream->expect(Token::NAME_TYPE, '_self');
            $forSelf = true;

            if (!$expr instanceof ConstantExpression) {
                throw new SyntaxError('The "for _self" modifier of the "trans_default_domain" tag requires a constant domain.', $token->getLine(), $source);
            }

            if ($this->registry->hasParsedEmbeddedTemplate($source)) {
                throw new SyntaxError('The "trans_default_domain" tag must be used with "for _self" before any "embed" tag.', $token->getLine(), $source);
            }

            $this->registry->setDomain($source, $expr->getAttribute('value'));
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new TransDefaultDomainNode($expr, $token->getLine(), $forSelf);
    }

    public function getTag(): string
    {
        return 'trans_default_domain';
    }
}
