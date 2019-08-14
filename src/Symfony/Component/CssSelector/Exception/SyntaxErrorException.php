<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Exception;

use Symfony\Component\CssSelector\Parser\Token;

/**
 * ParseException is thrown when a CSS selector syntax is not valid.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class SyntaxErrorException extends ParseException
{
    /**
     * @return self
     */
    public static function unexpectedToken(string $expectedValue, Token $foundToken)
    {
        return new self(sprintf('Expected %s, but %s found.', $expectedValue, $foundToken));
    }

    /**
     * @return self
     */
    public static function pseudoElementFound(string $pseudoElement, string $unexpectedLocation)
    {
        return new self(sprintf('Unexpected pseudo-element "::%s" found %s.', $pseudoElement, $unexpectedLocation));
    }

    /**
     * @return self
     */
    public static function unclosedString(int $position)
    {
        return new self(sprintf('Unclosed/invalid string at %s.', $position));
    }

    /**
     * @return self
     */
    public static function nestedNot()
    {
        return new self('Got nested ::not().');
    }

    /**
     * @return self
     */
    public static function stringAsFunctionArgument()
    {
        return new self('String not allowed as function argument.');
    }
}
