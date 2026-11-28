<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Dynamic;

use Symfony\Component\Form\FormEvents;

/**
 * Holds the configuration for a dynamic field and what listeners have been executed.
 *
 * @author Ryan Weaver
 * @author Benjamin Georgeault <git@wedgesama.fr>
 *
 * @internal
 */
final class DynamicFieldConfig
{
    public array $callbackExecuted = [
        FormEvents::PRE_SET_DATA => false,
        FormEvents::POST_SUBMIT => false,
    ];

    public function __construct(
        public string $name,
        public array $dependencies,
        public \Closure $callback,
    ) {
    }

    public function isReady(array $availableDependencyData, string $eventName): bool
    {
        if (!\array_key_exists($eventName, $this->callbackExecuted)) {
            throw new \InvalidArgumentException(\sprintf('Invalid event name "%s".', $eventName));
        }

        if ($this->callbackExecuted[$eventName]) {
            return false;
        }

        return array_all($this->dependencies, static fn ($dependency) => \array_key_exists($dependency, $availableDependencyData));
    }

    public function execute(array $availableDependencyData, string $eventName): DynamicField
    {
        $dynamicField = new DynamicField();
        $this->callbackExecuted[$eventName] = true;

        $dependencyData = array_map(static fn (string $dependency) => $availableDependencyData[$dependency], $this->dependencies);

        ($this->callback)($dynamicField, ...$dependencyData);

        return $dynamicField;
    }
}
