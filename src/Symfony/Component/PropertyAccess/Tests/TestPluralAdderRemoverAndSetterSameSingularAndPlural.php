<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests;

class TestPluralAdderRemoverAndSetterSameSingularAndPlural
{
    private array $aircraft = [];

    public function getAircraft()
    {
        return $this->aircraft;
    }

    public function setAircraft(array $aircraft): void
    {
        $this->aircraft = ['plane'];
    }

    public function addAircraft($aircraft): void
    {
        $this->aircraft[] = $aircraft;
    }

    public function removeAircraft($aircraft): void
    {
        $this->aircraft = array_diff($this->aircraft, [$aircraft]);
    }
}
