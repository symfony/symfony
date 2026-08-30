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

class ConstructorDummyWithPropertyDocBlock
{
    /** @var int */
    public $date;

    /** @var ConstructorDummy[] */
    public array $objectsArray;

    public function __construct(array $objectsArray, $date)
    {
        $this->objectsArray = $objectsArray;
        $this->date = $date;
    }
}
