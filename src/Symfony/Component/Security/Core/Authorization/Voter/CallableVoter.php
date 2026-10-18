<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\Security\Core\Attribute\AsVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Votes with the callable marked with {@see AsVoter}.
 *
 * @internal
 */
final class CallableVoter implements CacheableVoterInterface, \Stringable
{
    /**
     * @param list<'attribute'|'subject'|'token'|'vote'> $arguments    What the callable receives, parameter by parameter
     * @param list<string>                               $attributes   The security attributes the voter votes on
     * @param list<string>|null                          $subjectTypes The types of subject the voter votes on, null for all of them
     * @param string                                     $name         The name of the callable, shown by the profiler
     */
    public function __construct(
        private \Closure $callable,
        private array $arguments,
        private array $attributes,
        private ?array $subjectTypes,
        private string $name,
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        // use `::class` to handle anonymous classes, as the AccessDecisionManager does
        if (!$this->supportsType(\is_object($subject) ? $subject::class : get_debug_type($subject))) {
            return self::ACCESS_ABSTAIN;
        }

        $result = self::ACCESS_ABSTAIN;
        foreach ($attributes as $attribute) {
            if (!\is_string($attribute) || !$this->supportsAttribute($attribute)) {
                continue;
            }

            switch ($this->call($attribute, $subject, $token, $vote)) {
                case self::ACCESS_GRANTED:
                    return self::ACCESS_GRANTED;
                case self::ACCESS_DENIED:
                    $result = self::ACCESS_DENIED;
            }
        }

        return $result;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, $this->attributes, true);
    }

    public function supportsType(string $subjectType): bool
    {
        if (null === $this->subjectTypes) {
            return true;
        }

        foreach ($this->subjectTypes as $type) {
            // the AccessDecisionManager passes the class of an object, so only loaded classes are compared with is_a()
            if ($subjectType === $type || (class_exists($subjectType, false) && ('object' === $type || is_a($subjectType, $type, true)))) {
                return true;
            }
        }

        return false;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * @return self::ACCESS_*
     */
    private function call(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote): int
    {
        $arguments = [];
        foreach ($this->arguments as $argument) {
            $arguments[] = match ($argument) {
                'attribute' => $attribute,
                'subject' => $subject,
                'token' => $token,
                'vote' => $vote,
            };
        }

        $result = ($this->callable)(...$arguments);

        return match ($result) {
            true => self::ACCESS_GRANTED,
            false => self::ACCESS_DENIED,
            null => self::ACCESS_ABSTAIN,
            self::ACCESS_GRANTED, self::ACCESS_DENIED, self::ACCESS_ABSTAIN => $result,
            default => throw new \LogicException(\sprintf('"%s()" must return a boolean, null or one of the "%s::ACCESS_*" constants, "%s" returned.', $this->name, VoterInterface::class, get_debug_type($result))),
        };
    }
}
