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
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class SyntaxErrorException extends \Exception
{
    /**
     * @param string $expectedValue
     * @param Token  $foundToken
     *
     * @return SyntaxErrorException
     */
    public static function unexpectedToken($expectedValue, Token $foundToken)
    {
        return new self(sprintf('Unexpected token, "%s" expected but "%s" found.', $expectedValue, $foundToken));
    }

    /**
     * @param string $pseudoElement
     * @param string $unexpectedLocation
     *
     * @return SyntaxErrorException
     */
    public static function pseudoElementFound($pseudoElement, $unexpectedLocation)
    {
        return new self(sprintf('Unexpected pseudo-element "::%s" found %s.', $pseudoElement, $unexpectedLocation));
    }

    /**
     * @return SyntaxErrorException
     */
    public static function nestedNot()
    {
        return new self('Got nested ::not().');
    }

    /**
     * @return SyntaxErrorException
     */
    public static function stringAsFunctionArgument()
    {
        return new self('String not allowed as function argument.');
    }
}
