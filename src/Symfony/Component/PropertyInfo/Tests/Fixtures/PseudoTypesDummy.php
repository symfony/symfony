<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Emil Masiakowski <emil.masiakowski@gmail.com>
 */
class PseudoTypesDummy
{
    /** @var class-string */
    public $classString;

    /** @var class-string<\stdClass> */
    public $classStringGeneric;

    /** @var html-escaped-string */
    public $htmlEscapedString;

    /** @var lowercase-string */
    public $lowercaseString;

    /** @var non-empty-lowercase-string */
    public $nonEmptyLowercaseString;

    /** @var non-empty-string */
    public $nonEmptyString;

    /** @var numeric-string */
    public $numericString;

    /** @var trait-string */
    public $traitString;

    /** @var positive-int */
    public $positiveInt;

    /** @var literal-string */
    public $literalString;
}
