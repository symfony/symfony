<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Debug;

/**
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
final class AutowiringTypeInfo
{
    private $type;

    private $name;

    private $priority;

    private $description = '';

    public function __construct(string $type, string $name, int $priority = 0)
    {
        $this->type = $type;
        $this->name = $name;
        $this->priority = $priority;
    }

    /**
     * @param string $type     The class/interface for the type
     * @param string $name     A very short name to describe this (e.g. Logger or Annotation Reader)
     * @param int    $priority A priority for how important this service is
     *
     * @return static
     */
    public static function create(string $type, string $name, int $priority = 0)
    {
        return new static($type, $name, $priority);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
