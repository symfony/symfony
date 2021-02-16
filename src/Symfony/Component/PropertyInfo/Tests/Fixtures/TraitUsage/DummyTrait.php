<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage;

use Symfony\Component\PropertyInfo\Tests\Fixtures\Dummy;

trait DummyTrait
{
    /**
     * @var string
     */
    private $propertyInTraitPrimitiveType;

    /**
     * @var DummyUsedInTrait
     */
    private $propertyInTraitObjectSameNamespace;

    /**
     * @var Dummy
     */
    private $propertyInTraitObjectDifferentNamespace;
}
