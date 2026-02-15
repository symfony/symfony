<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestIgnoreVoidAccessor
{
    public bool $setValue = false;
    public bool $setterValue = false;
    public bool $neverValue = false;
    public bool $normalValue = false;

    public function setValue(): void
    {
        $this->setValue = true;
    }

    public function setSetterValue(): void
    {
        $this->setterValue = true;
    }

    public function setNeverValue(): never
    {
        // Simulate a setter that does not return anything and exits
        $this->neverValue = true;
        exit;
    }

    public function setUndefinedValue(): void
    {
        // This method is intentionally left empty to simulate a missing setter
    }
}
