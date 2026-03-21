<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler;

use Symfony\Contracts\Deprecation\IssueTriggerContextInterface;

/**
 * Carries the context of a deprecation triggered by DebugClassLoader.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
final class DebugClassLoaderDeprecationNotice implements IssueTriggerContextInterface, \Stringable
{
    /**
     * @param string      $loadingClass    The class currently being loaded (the "consumer", e.g. the child class)
     * @param string|null $triggeringClass The class that caused the deprecation (e.g. the deprecated parent or interface),
     *                                     or null when that information is not available
     */
    public function __construct(
        private readonly string $message,
        public readonly string $loadingClass,
        public readonly ?string $triggeringClass,
    ) {
    }

    public function getCalleeFile(): ?string
    {
        return $this->fileForClass($this->triggeringClass);
    }

    public function getCallerFile(): ?string
    {
        return $this->fileForClass($this->loadingClass);
    }

    public function __toString(): string
    {
        return $this->message;
    }

    private function fileForClass(?string $class): ?string
    {
        if (null === $class) {
            return null;
        }

        return (new \ReflectionClass($class))->getFileName() ?: null;
    }
}
