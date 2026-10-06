<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Enricher;

use Symfony\Component\TypeInfo\Type;

/**
 * The schema of a property while it goes through the enrichers.
 *
 * @experimental
 */
final readonly class PropertySchema
{
    /**
     * @param class-string         $class
     * @param array<string, mixed> $schema
     */
    public function __construct(
        public string $class,
        public string $property,
        public ?Type $type,
        public array $schema,
        public bool $required,
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function withSchema(array $schema): self
    {
        return new self($this->class, $this->property, $this->type, $schema, $this->required);
    }

    public function withRequired(bool $required): self
    {
        return new self($this->class, $this->property, $this->type, $this->schema, $required);
    }
}
