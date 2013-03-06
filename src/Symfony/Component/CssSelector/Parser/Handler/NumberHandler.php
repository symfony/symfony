<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Token\Handler;

use Symfony\Component\CssSelector\Token\Reader;
use Symfony\Component\CssSelector\Token\Token;
use Symfony\Component\CssSelector\Token\TokenStream;
use Symfony\Component\CssSelector\Token\TokenizerEscaping;
use Symfony\Component\CssSelector\Token\TokenizerPatterns;

/**
 * CSS selector comment handler.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class NumberHandler implements HandlerInterface
{
    /**
     * @var TokenizerPatterns
     */
    private $patterns;

    /**
     * @param TokenizerPatterns $patterns
     */
    public function __construct(TokenizerPatterns $patterns)
    {
        $this->patterns = $patterns;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Reader $reader, TokenStream $stream)
    {
        $match = $reader->findPattern($this->patterns->getHashPattern());

        if (!$match) {
            return false;
        }

        $stream->push(new Token(Token::TYPE_NUMBER, $match[0], $reader->getPosition()));
        $reader->moveForward(strlen($match[0]));

        return true;
    }
}
