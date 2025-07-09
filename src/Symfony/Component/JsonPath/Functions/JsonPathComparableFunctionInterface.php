<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Functions;

/**
 * Interface for functions that return values that must be compared (not used as
 * standalone boolean expressions). These functions will be validated to ensure they are used in
 * comparison expressions, such as `==`, `!=`, `<`, `<=`, `>`, or `>=`.
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
interface JsonPathComparableFunctionInterface extends JsonPathFunctionInterface
{
}
