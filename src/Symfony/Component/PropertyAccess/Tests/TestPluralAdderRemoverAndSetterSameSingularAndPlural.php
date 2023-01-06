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
    private $aircraft = [];

    public function getAircraft()
    {
        return $this->aircraft;
    }

    public function setAircraft(array $aircraft)
    {
        $this->aircraft = ['plane'];
    }

    public function addAircraft($aircraft)
    {
        $this->aircraft[] = $aircraft;
    }

    public function removeAircraft($aircraft)
    {
        $this->aircraft = array_diff($this->aircraft, [$aircraft]);
    }
}
