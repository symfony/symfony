<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Mapping;

use Symfony\Component\JsonEncoder\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Type;

/**
 * Holds encoding/decoding metadata about a given property.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final readonly class PropertyMetadata
{
    /**
     * @param list<callable> $formatters
     */
    public function __construct(
        public string $name,
        public Type $type,
        public array $formatters = [],
    ) {
        self::validateFormatters($this);
    }

    public function withName(string $name): self
    {
        /** @var array{name: string, type: Type, formatters: list<callable>} */
        $args = (array) $this;
        $args['name'] = $name;

        return new self(...$args);
    }

    public function withType(Type $type): self
    {
        /** @var array{name: string, type: Type, formatters: list<callable>} */
        $args = (array) $this;
        $args['type'] = $type;

        return new self(...$args);
    }

    /**
     * @param list<callable> $formatters
     */
    public function withFormatters(array $formatters): self
    {
        /** @var array{name: string, type: Type, formatters: list<callable>} */
        $args = (array) $this;
        $args['formatters'] = $formatters;

        return new self(...$args);
    }

    public function withFormatter(callable $formatter): self
    {
        $formatters = $this->formatters;
        $formatters[] = $formatter;

        return $this->withFormatters($formatters);
    }

    private static function validateFormatters(self $metadata): void
    {
        foreach ($metadata->formatters as $formatter) {
            $reflection = new \ReflectionFunction(\Closure::fromCallable($formatter));

            if ($reflection->getClosureScopeClass()?->hasMethod($reflection->getName())) {
                if (!$reflection->isStatic()) {
                    throw new InvalidArgumentException(sprintf('"%s"\'s property formatter must be a static method.', $metadata->name));
                }

                continue;
            }

            if ($reflection->isAnonymous()) {
                throw new InvalidArgumentException(sprintf('"%s"\'s property formatter must not be anonymous.', $metadata->name));
            }
        }
    }
}
