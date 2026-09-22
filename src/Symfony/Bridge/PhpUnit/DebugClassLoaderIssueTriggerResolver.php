<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit;

use PHPUnit\Runner\IssueTriggerResolver\Resolution;
use PHPUnit\Runner\IssueTriggerResolver\Resolver;
use Symfony\Component\ErrorHandler\DebugClassLoader;

if (interface_exists(Resolver::class) && class_exists(Resolution::class)) {
    /**
     * Resolves the semantic caller and callee of deprecations triggered by DebugClassLoader.
     *
     * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
     */
    final class DebugClassLoaderIssueTriggerResolver implements Resolver
    {
        /**
         * @param list<array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $trace
         */
        public function resolve(array $trace, string $message): ?Resolution
        {
            $caller = $this->caller($trace, $message);

            if (null === $caller) {
                return null;
            }

            [$callerClass, $callerFile] = $caller;
            $calleeClass = $this->calleeClass($message) ?? $callerClass;

            if (!$this->classExists($callerClass) || !$this->classExists($calleeClass)) {
                return null;
            }

            $callerFile ??= (new \ReflectionClass($callerClass))->getFileName();

            if (false === $callerFile) {
                return null;
            }

            return new Resolution(
                (new \ReflectionClass($calleeClass))->getFileName() ?: null,
                $callerFile,
            );
        }

        /**
         * @param list<array{function: string, line?: int, file?: string, class?: class-string, type?: '->'|'::', args?: list<mixed>, object?: object}> $trace
         *
         * @return array{string, ?string}|null
         */
        private function caller(array $trace, string $message): ?array
        {
            foreach ($trace as $i => $frame) {
                if (
                    0 === $i
                    || DebugClassLoader::class !== ($frame['class'] ?? null)
                    || 'checkClass' !== $frame['function']
                ) {
                    continue;
                }

                $trigger = $trace[$i - 1];

                if (
                    isset($trigger['class'])
                    || 'trigger_error' !== $trigger['function']
                    || $message !== ($trigger['args'][0] ?? null)
                    || \E_USER_DEPRECATED !== ($trigger['args'][1] ?? null)
                ) {
                    continue;
                }

                if (!\is_string($class = $frame['args'][0] ?? null)) {
                    return null;
                }

                $file = $frame['args'][1] ?? null;

                return [$class, \is_string($file) && '' !== $file ? realpath($file) ?: null : null];
            }

            return null;
        }

        private function calleeClass(string $message): ?string
        {
            if (preg_match('/^The "[^"]++" (?:class|interface|trait) (?:extends|implements|uses) "([^"]++)" that is deprecated/', $message, $matches)) {
                return $matches[1];
            }

            if (preg_match('/^Class "[^"]++" should implement method "(?:static )?([^:]++)::/', $message, $matches)) {
                return $matches[1];
            }

            if (preg_match('/^(?:The|Method) "([^":]++)/', $message, $matches)) {
                return $matches[1];
            }

            return null;
        }

        private function classExists(string $class): bool
        {
            return class_exists($class, false) || interface_exists($class, false) || trait_exists($class, false);
        }
    }
} else {
    throw new \LogicException(\sprintf('You cannot use the "%s\\DebugClassLoaderIssueTriggerResolver" class as PHPUnit 13.1 or higher is not installed.', __NAMESPACE__));
}
