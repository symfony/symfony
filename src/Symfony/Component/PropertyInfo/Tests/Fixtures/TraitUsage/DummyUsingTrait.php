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

use Symfony\Component\PropertyInfo\Tests\Fixtures\TraitUsage\AnotherNamespace\DummyTraitInAnotherNamespace;

class DummyUsingTrait
{
    use DummyTrait;
    use DummyTraitInAnotherNamespace;
}
