<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

/**
 * Value object used to store an attribute configuration.
 *
 * @internal
 *
 * @author Bertrand Seurot <b.seurot@gmail.com>
 */
class AttributeConfiguration
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string[]
     */
    private $groups;

    /**
     * @var int|null
     */
    private $maxDepth;

    /**
     * @var string|null
     */
    private $serializedName;

    /**
     * AttributeConfiguration constructor.
     *
     * @param string[]|null $groups
     */
    public function __construct(string $name, ?array $groups = null, ?int $maxDepth = null, ?string $serializedName = null)
    {
        $this->name = $name;
        $this->groups = $groups ?? [];
        $this->maxDepth = $maxDepth;
        $this->serializedName = $serializedName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    public function getMaxDepth(): ?int
    {
        return $this->maxDepth;
    }

    public function getSerializedName(): ?string
    {
        return $this->serializedName;
    }
}
