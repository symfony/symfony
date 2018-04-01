<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\TokenParser;

use Symphony\Bridge\Twig\Node\StopwatchNode;
use Twig\Node\Expression\AssignNameExpression;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Token Parser for the stopwatch tag.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class StopwatchTokenParser extends AbstractTokenParser
{
    protected $stopwatchIsAvailable;

    public function __construct(bool $stopwatchIsAvailable)
    {
        $this->stopwatchIsAvailable = $stopwatchIsAvailable;
    }

    public function parse(Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        // {% stopwatch 'bar' %}
        $name = $this->parser->getExpressionParser()->parseExpression();

        $stream->expect(Token::BLOCK_END_TYPE);

        // {% endstopwatch %}
        $body = $this->parser->subparse(array($this, 'decideStopwatchEnd'), true);
        $stream->expect(Token::BLOCK_END_TYPE);

        if ($this->stopwatchIsAvailable) {
            return new StopwatchNode($name, $body, new AssignNameExpression($this->parser->getVarName(), $token->getLine()), $lineno, $this->getTag());
        }

        return $body;
    }

    public function decideStopwatchEnd(Token $token)
    {
        return $token->test('endstopwatch');
    }

    public function getTag()
    {
        return 'stopwatch';
    }
}
