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

/**
 * Token Parser for the stopwatch tag.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class StopwatchTokenParser extends \Twig_TokenParser
{
    protected $stopwatchIsAvailable;

    public function __construct($stopwatchIsAvailable)
    {
        $this->stopwatchIsAvailable = $stopwatchIsAvailable;
    }

    public function parse(\Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        // {% stopwatch 'bar' %}
        $name = $this->parser->getExpressionParser()->parseExpression();

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        // {% endstopwatch %}
        $body = $this->parser->subparse(array($this, 'decideStopwatchEnd'), true);
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        if ($this->stopwatchIsAvailable) {
            return new StopwatchNode($name, $body, new \Twig_Node_Expression_AssignName($this->parser->getVarName(), $token->getLine()), $lineno, $this->getTag());
        }

        return $body;
    }

    public function decideStopwatchEnd(\Twig_Token $token)
    {
        return $token->test('endstopwatch');
    }

    public function getTag()
    {
        return 'stopwatch';
    }
}
