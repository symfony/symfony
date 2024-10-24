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

use Symfony\Bridge\Twig\Node\StopwatchNode;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the stopwatch tag.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
final class StopwatchTokenParser extends AbstractTokenParser
{
    public function __construct(
        private bool $stopwatchIsAvailable,
    ) {
    }

    public function parse(Token $token): Node
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        // {% stopwatch 'bar' %}
        $name = $this->parser->getExpressionParser()->parseExpression();

        $stream->expect(Token::BLOCK_END_TYPE);

        // {% endstopwatch %}
        $body = $this->parser->subparse($this->decideStopwatchEnd(...), true);
        $stream->expect(Token::BLOCK_END_TYPE);

        if ($this->stopwatchIsAvailable) {
            return new StopwatchNode($name, $body, class_exists(AssignContextVariable::class) ? new AssignContextVariable($this->getVarName(), $token->getLine()) : new AssignNameExpression($this->getVarName(), $token->getLine()), $lineno, $this->getTag());
        }

        return $body;
    }

    public function decideStopwatchEnd(Token $token): bool
    {
        return $token->test('endstopwatch');
    }

    public function getTag(): string
    {
        return 'stopwatch';
    }

    private function getVarName(): string
    {
        return sprintf('__internal_%s', hash('sha256', uniqid(mt_rand(), true)));
    }
}
