<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo;

/**
 * A PHPDoc refinement that the string type resolver can resolve, instead of the builtin type it refines.
 *
 * Each case is opted in to on its own, so that a refinement added later does not change the types of
 * applications that opted in to the existing ones.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
enum Refinement: string
{
    /**
     * String, integer and float literals: "'draft'", "1" and "1.5". References to class constants are not covered.
     */
    case Literal = 'literal';

    /**
     * Integer ranges: "int<min, max>", "positive-int", "negative-int", "non-positive-int", "non-negative-int" and "non-zero-int".
     */
    case IntRange = 'int_range';
}
