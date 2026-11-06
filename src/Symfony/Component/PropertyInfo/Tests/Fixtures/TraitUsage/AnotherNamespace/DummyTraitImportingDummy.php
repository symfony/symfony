<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace;

use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace\DummyInAnotherNamespace as Dummy;

trait DummyTraitImportingDummy
{
    /**
     * @var Dummy
     */
    public $dummyFromTrait;
}
