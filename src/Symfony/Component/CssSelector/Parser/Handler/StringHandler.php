<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser\Handler;

use Symfony\Component\CssSelector\Parser\Reader;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;
use Symfony\Component\CssSelector\Parser\TokenizerEscaping;
use Symfony\Component\CssSelector\Parser\TokenizerPatterns;

/**
 * CSS selector comment handler.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class StringHandler implements HandlerInterface
{
    /**
     * @var TokenizerPatterns
     */
    private $patterns;

    /**
     * @var TokenizerEscaping
     */
    private $escaping;

    /**
     * @param TokenizerPatterns $patterns
     * @param TokenizerEscaping $escaping
     */
    public function __construct(TokenizerPatterns $patterns, TokenizerEscaping $escaping)
    {
        $this->patterns = $patterns;
        $this->escaping = $escaping;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Reader $reader, TokenStream $stream)
    {
        $quote = $reader->getSubstring(1);

        if (!in_array($quote, array("'", '"'))) {
            return false;
        }

        $reader->moveForward(1);
        $match = $reader->findPattern($this->patterns->getQuotedStringPattern($quote));

        if (!$match) {
            throw new \LogicException('Should have found at least an empty match');
        }

        // check unclosed strings
        if (strlen($match[0]) === $reader->getRemainingLength()) {
            throw new \LogicException('Unclosed string at '.$reader->getPosition());
        }

        // check quotes pairs validity
        if ($quote !== $reader->getSubstring(1, strlen($match[0]))) {
            throw new \LogicException('Invalid string at '.$reader->getPosition());
        }

        $string = $this->escaping->escapeUnicodeAndNewLine($match[0]);
        $stream->push(new Token(Token::TYPE_STRING, $string, $reader->getPosition()));
        $reader->moveForward(strlen($match[0]) + 1);

        return true;
    }
}
