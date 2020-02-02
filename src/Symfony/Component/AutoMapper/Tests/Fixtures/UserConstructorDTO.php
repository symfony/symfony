<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Fixtures;

class UserConstructorDTO
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var ?string
     */
    private $name;

    /**
     * @var int
     */
    private $age;

    /**
     * @var bool
     */
    private $constructor = false;

    public function __construct(string $id, string $name, int $age = 30)
    {
        $this->id = $id;
        $this->name = $name;
        $this->age = $age;
        $this->constructor = true;
    }

    /**
     * @return int
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return int|null
     */
    public function getAge()
    {
        return $this->age;
    }

    public function getConstructor(): bool
    {
        return $this->constructor;
    }
}
