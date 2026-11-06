<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\Fixtures\AnotherNamespace;

use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;

trait DummyTraitWithUses
{
    /**
     * @var Dummy
     */
    public mixed $dummyFromTrait;

    /**
     * @var DummyBackedEnum
     */
    public mixed $enumFromTrait;
}
