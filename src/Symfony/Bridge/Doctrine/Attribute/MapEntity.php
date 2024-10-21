<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Attribute;

use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Indicates that a controller argument should receive an Entity.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapEntity extends ValueResolver
{
    /**
     * @param class-string|null          $class         The entity class
     * @param string|null                $objectManager Specify the object manager used to retrieve the entity
     * @param string|null                $expr          An expression to fetch the entity using the {@see https://symfony.com/doc/current/components/expression_language.html ExpressionLanguage} syntax.
     *                                                  Any request attribute are available as a variable, and your entity repository in the 'repository' variable.
     * @param array<string, string>|null $mapping       Configures the properties and values to use with the findOneBy() method
     *                                                  The key is the route placeholder name and the value is the Doctrine property name
     * @param string[]|null              $exclude       Configures the properties that should be used in the findOneBy() method by excluding
     *                                                  one or more properties so that not all are used
     * @param bool|null                  $stripNull     Whether to prevent null values from being used as parameters in the query (defaults to false)
     * @param string[]|string|null       $id            If an id option is configured and matches a route parameter, then the resolver will find by the primary key
     * @param bool|null                  $evictCache    If true, forces Doctrine to always fetch the entity from the database instead of cache (defaults to false)
     */
    public function __construct(
        public ?string $class = null,
        public ?string $objectManager = null,
        public ?string $expr = null,
        public ?array $mapping = null,
        public ?array $exclude = null,
        public ?bool $stripNull = null,
        public array|string|null $id = null,
        public ?bool $evictCache = null,
        bool $disabled = false,
        string $resolver = EntityValueResolver::class,
        public ?string $message = null,
    ) {
        parent::__construct($resolver, $disabled);
        $this->selfValidate();
    }

    public function withDefaults(self $defaults, ?string $class): static
    {
        $clone = clone $this;
        $clone->class ??= class_exists($class ?? '') || interface_exists($class ?? '', false) ? $class : null;
        $clone->objectManager ??= $defaults->objectManager;
        $clone->expr ??= $defaults->expr;
        $clone->mapping ??= $defaults->mapping;
        $clone->exclude ??= $defaults->exclude ?? [];
        $clone->stripNull ??= $defaults->stripNull ?? false;
        $clone->id ??= $defaults->id;
        $clone->evictCache ??= $defaults->evictCache ?? false;
        $clone->message ??= $defaults->message;

        $clone->selfValidate();

        return $clone;
    }

    private function selfValidate(): void
    {
        if (!$this->id) {
            return;
        }
        if ($this->mapping) {
            throw new \LogicException('The "id" and "mapping" options cannot be used together on #[MapEntity] attributes.');
        }
        if ($this->exclude) {
            throw new \LogicException('The "id" and "exclude" options cannot be used together on #[MapEntity] attributes.');
        }
        $this->mapping = [];
    }
}
