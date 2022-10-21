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
class PhpStanPseudoTypesDummy extends PseudoTypesDummy
{
    /** @var negative-int */
    public $negativeInt;

    /** @var non-empty-array */
    public $nonEmptyArray;

    /** @var non-empty-list */
    public $nonEmptyList;

    /** @var interface-string */
    public $interfaceString;

    /** @var scalar */
    public $scalar;

    /** @var array-key */
    public $arrayKey;

    /** @var number */
    public $number;

    /** @var numeric */
    public $numeric;

    /** @var double */
    public $double;
}
