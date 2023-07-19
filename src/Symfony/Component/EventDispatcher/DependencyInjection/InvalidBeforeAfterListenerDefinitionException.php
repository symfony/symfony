<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * @psalm-import-type BeforeAfterDefinition from RegisterListenersPass
 */
final class InvalidBeforeAfterListenerDefinitionException extends InvalidArgumentException
{
    private function __construct(string $errorServiceId, string $message)
    {
        parent::__construct(sprintf('Invalid before/after definition for service "%s": %s', $errorServiceId, $message));
    }

    public static function beforeAndAfterAtSameTime(string $errorServiceId): self
    {
        return new self($errorServiceId, 'cannot use "after" and "before" at the same time.');
    }

    public static function circularReference(string $errorServiceId): self
    {
        return new self($errorServiceId, 'circular reference detected.');
    }

    public static function arrayDefinitionInvalid(string $errorServiceId): self
    {
        return new self($errorServiceId, 'when declaring as an array, first item must be a service id or a class and second item must be the method.');
    }

    /**
     * @param BeforeAfterDefinition $beforeAfterDefinition
     */
    public static function notAListener(string $errorServiceId, string|array $beforeAfterDefinition): self
    {
        return new self($errorServiceId, sprintf('given definition "%s" is not a listener.', self::beforeAfterDefinitionToString($beforeAfterDefinition)));
    }

    /**
     * @param BeforeAfterDefinition $beforeAfterDefinition
     */
    public static function notSameEvent(string $errorServiceId, string|array $beforeAfterDefinition): self
    {
        return new self($errorServiceId, sprintf('given definition "%s" does not listen to the same event.', self::beforeAfterDefinitionToString($beforeAfterDefinition)));
    }

    /**
     * @param BeforeAfterDefinition $beforeAfterDefinition
     */
    public static function notSameDispatchers(string $errorServiceId, string|array $beforeAfterDefinition): self
    {
        return new self($errorServiceId, sprintf('given definition "%s" is not handled by the same dispatchers.', self::beforeAfterDefinitionToString($beforeAfterDefinition)));
    }

    public static function ambiguousDefinition(string $errorServiceId, string $beforeAfterDefinition): self
    {
        return new self($errorServiceId, sprintf('given definition "%s" is ambiguous. Please specify the "method" attribute.', $beforeAfterDefinition));
    }

    /**
     * @param BeforeAfterDefinition $beforeAfterDefinition
     */
    private static function beforeAfterDefinitionToString(string|array $beforeAfterDefinition): string
    {
        if (\is_string($beforeAfterDefinition)) {
            return $beforeAfterDefinition;
        }

        return sprintf('%s::%s()', $beforeAfterDefinition[0], $beforeAfterDefinition[1]);
    }
}
