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
use Symfony\Component\PropertyInfo\Tests\Fixtures\DummyTraitExternal;

trait DummyTrait
{
    use DummyTraitExternal;

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

    /**
     * @return string
     */
    public function getMethodInTraitPrimitiveType()
    {
        return 'value';
    }

    /**
     * @return DummyUsedInTrait
     */
    public function getMethodInTraitObjectSameNamespace()
    {
        return new DummyUsedInTrait();
    }

    /**
     * @return Dummy
     */
    public function getMethodInTraitObjectDifferentNamespace()
    {
        return new Dummy();
    }
}
