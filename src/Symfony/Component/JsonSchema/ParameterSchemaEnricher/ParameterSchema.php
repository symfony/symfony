<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\ParameterSchemaEnricher;

use Symfony\Component\TypeInfo\Type;

/**
 * The schema of a parameter while it goes through the enrichers.
 *
 * @experimental
 */
final class ParameterSchema
{
    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(
        public readonly \ReflectionParameter $parameter,
        public readonly ?Type $type,
        public readonly array $schema,
        public readonly bool $required,
    ) {
    }

    /**
     * @param array<string, mixed> $schema
     */
    public function withSchema(array $schema): self
    {
        return new self($this->parameter, $this->type, $schema, $this->required);
    }

    public function withRequired(bool $required): self
    {
        return new self($this->parameter, $this->type, $this->schema, $required);
    }
}
