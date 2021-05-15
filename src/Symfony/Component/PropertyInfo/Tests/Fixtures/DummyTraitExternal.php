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

use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\DummyUsedInTrait;

trait DummyTraitExternal
{
    /**
     * @var string
     */
    private $propertyInExternalTraitPrimitiveType;

    /**
     * @var Dummy
     */
    private $propertyInExternalTraitObjectSameNamespace;

    /**
     * @var DummyUsedInTrait
     */
    private $propertyInExternalTraitObjectDifferentNamespace;

    /**
     * @return string
     */
    public function getMethodInExternalTraitPrimitiveType()
    {
        return 'value';
    }

    /**
     * @return Dummy
     */
    public function getMethodInExternalTraitObjectSameNamespace()
    {
        return new Dummy();
    }

    /**
     * @return DummyUsedInTrait
     */
    public function getMethodInExternalTraitObjectDifferentNamespace()
    {
        return new DummyUsedInTrait();
    }
}
