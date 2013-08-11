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

        // {% stopwatch bar %}
        if ($stream->test(\Twig_Token::NAME_TYPE)) {
            $name = $stream->expect(\Twig_Token::NAME_TYPE)->getValue();
        } else {
            $name = $stream->expect(\Twig_Token::STRING_TYPE)->getValue();
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        // {% endstopwatch %} or {% endstopwatch bar %}
        $body = $this->parser->subparse(array($this, 'decideStopwatchEnd'), true);
        if ($stream->test(\Twig_Token::NAME_TYPE) || $stream->test(\Twig_Token::STRING_TYPE)) {
            $value = $stream->next()->getValue();

            if ($name != $value) {
                throw new \Twig_Error_Syntax(sprintf('Expected endstopwatch for event "%s" (but "%s" given).', $name, $value));
            }
        }
        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        if ($this->stopwatchIsAvailable) {
            return new StopwatchNode($name, $body, $lineno, $this->getTag());
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
