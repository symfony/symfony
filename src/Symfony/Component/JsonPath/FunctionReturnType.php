<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath;

/**
 * Declares the return type of a custom JsonPath function as defined in RFC 9535.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9535#name-type-system-for-function-ex
 */
enum FunctionReturnType: string
{
    /**
     * A JSON value that can be used in comparisons.
     */
    case Value = 'value';

    /**
     * A boolean result usable as a filter expression (e.g. existence tests).
     */
    case Logical = 'logical';

    /**
     * A nodelist (array of matched nodes), not directly comparable.
     */
    case Nodes = 'nodes';
}
