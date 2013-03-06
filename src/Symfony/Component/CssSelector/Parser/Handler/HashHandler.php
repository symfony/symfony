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
class HashHandler implements HandlerInterface
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
        $match = $reader->findPattern($this->patterns->getHashPattern());

        if (!$match) {
            return false;
        }

        $value = $this->escaping->escapeUnicode($match[1]);
        $stream->push(new Token(Token::TYPE_HASH, $value, $reader->getPosition()));
        $reader->moveForward(strlen($match[0]));

        return true;
    }
}
