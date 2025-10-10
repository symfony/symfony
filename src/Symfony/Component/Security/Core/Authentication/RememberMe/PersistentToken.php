<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\RememberMe;

/**
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @internal
 */
final class PersistentToken implements PersistentTokenInterface
{
    private ?string $class = null;
    private string $userIdentifier;
    private string $series;
    private string $tokenValue;
    private \DateTimeImmutable $lastUsed;

    /**
     * @param string             $userIdentifier
     * @param string             $series
     * @param string             $tokenValue
     * @param \DateTimeInterface $lastUsed
     */
    public function __construct(
        $userIdentifier,
        $series,
        #[\SensitiveParameter] $tokenValue,
        #[\SensitiveParameter] $lastUsed,
    ) {
        if (\func_num_args() > 4) {
            if (\func_num_args() < 6 || func_get_arg(5)) {
                trigger_deprecation('symfony/security-core', '7.4', 'Passing a user FQCN to %s() is deprecated. The user class will be removed from the remember-me cookie in 8.0.', __CLASS__, __NAMESPACE__);
            }

            if (!\is_string($userIdentifier)) {
                throw new \TypeError(\sprintf('Argument 1 passed to "%s()" must be a string, "%s" given.', __METHOD__, get_debug_type($userIdentifier)));
            }

            $this->class = $userIdentifier;
            $userIdentifier = $series;
            $series = $tokenValue;
            $tokenValue = $lastUsed;

            if (\func_num_args() <= 4) {
                throw new \TypeError(\sprintf('Argument 5 passed to "%s()" must be an instance of "%s", the argument is missing.', __METHOD__, \DateTimeInterface::class));
            }

            $lastUsed = func_get_arg(4);
        }

        if (!\is_string($userIdentifier)) {
            throw new \TypeError(\sprintf('The $userIdentifier argument passed to "%s()" must be a string, "%s" given.', __METHOD__, get_debug_type($userIdentifier)));
        }

        if (!\is_string($series)) {
            throw new \TypeError(\sprintf('The $series argument  passed to "%s()" must be a string, "%s" given.', __METHOD__, get_debug_type($series)));
        }

        if (!\is_string($tokenValue)) {
            throw new \TypeError(\sprintf('The $tokenValue argument  passed to "%s()" must be a string, "%s" given.', __METHOD__, get_debug_type($tokenValue)));
        }

        if (!$lastUsed instanceof \DateTimeInterface) {
            throw new \TypeError(\sprintf('The $lastUsed argument  passed to "%s()" must be an instance of "%s", "%s" given.', __METHOD__, \DateTimeInterface::class, get_debug_type($lastUsed)));
        }

        if ('' === $userIdentifier) {
            throw new \InvalidArgumentException('$userIdentifier must not be empty.');
        }
        if (!$series) {
            throw new \InvalidArgumentException('$series must not be empty.');
        }
        if (!$tokenValue) {
            throw new \InvalidArgumentException('$tokenValue must not be empty.');
        }

        $this->userIdentifier = $userIdentifier;
        $this->series = $series;
        $this->tokenValue = $tokenValue;
        $this->lastUsed = \DateTimeImmutable::createFromInterface($lastUsed);
    }

    /**
     * @deprecated since Symfony 7.4
     */
    public function getClass(bool $triggerDeprecation = true): string
    {
        if ($triggerDeprecation) {
            trigger_deprecation('symfony/security-core', '7.4', 'The "%s()" method is deprecated: the user class will be removed from the remember-me cookie in 8.0.', __METHOD__);
        }

        return $this->class ?? '';
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getTokenValue(): string
    {
        return $this->tokenValue;
    }

    public function getLastUsed(): \DateTime
    {
        return \DateTime::createFromImmutable($this->lastUsed);
    }
}
