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

class DummyWithSingularAndPlural
{
    private ?Dummy $network = null;
    private array $networks = [];
    private array $tags = [];
    private array $staff = [];

    public function getNetwork(): ?Dummy
    {
        return $this->network;
    }

    public function setNetwork(?Dummy $network): static
    {
        $this->network = $network;

        return $this;
    }

    public function getNetworks(): array
    {
        return $this->networks;
    }

    public function setNetworks(array $networks): static
    {
        $this->networks = $networks;

        return $this;
    }

    public function addNetwork(Dummy $network): static
    {
        $this->networks[] = $network;

        return $this;
    }

    public function removeNetwork(Dummy $network): static
    {
        return $this;
    }

    public function addTag(string $tag): void
    {
    }

    public function removeTag(string $tag): void
    {
    }

    public function setAircraft(array $aircraft): void
    {
    }

    public function addAircraft(string $aircraft): void
    {
    }

    public function removeAircraft(string $aircraft): void
    {
    }

    public function setStaff(array $staff): void
    {
        $this->staff = $staff;
    }

    public function addStaff(string $staff): void
    {
        $this->staff[] = $staff;
    }

    public function removeStaff(string $staff): void
    {
        $this->staff = array_diff($this->staff, [$staff]);
    }
}
