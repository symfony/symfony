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
     * Literals, such as "'draft'", "1" or "1.5".
     */
    case Literal = 'literal';

    /**
     * Integer ranges, such as "int<0, 10>" or "positive-int".
     */
    case IntRange = 'int_range';
}
