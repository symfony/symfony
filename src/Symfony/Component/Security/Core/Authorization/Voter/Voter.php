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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Voter is an abstract default implementation of a voter.
 *
 * @author Roman Marintšenko <inoryy@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @template TAttribute of mixed
 * @template TSubject of mixed
 */
abstract class Voter implements VoterInterface, CacheableVoterInterface
{
    public function __construct()
    {
        if ('string' === (string) (new \ReflectionMethod(static::class, 'supports'))->getParameters()[0]->getType()) {
            trigger_deprecation('symfony/security-core', '6.3', 'Using string as first parameter type in "%s::supports()" class is deprecated, use "mixed" instead.', static::class);
        }

        if ('string' === (string) (new \ReflectionMethod(static::class, 'voteOnAttribute'))->getParameters()[0]->getType()) {
            trigger_deprecation('symfony/security-core', '6.3', 'Using string as first parameter type in "%s::voteOnAttribute()" class is deprecated, use "mixed" instead.', static::class);
        }
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        // abstain vote by default in case none of the attributes are supported
        $vote = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            try {
                if (!$this->supports($attribute, $subject)) {
                    continue;
                }
            } catch (\TypeError $e) {
                // @deprecated since Symfony 6.3, the try/catch should be removed in 7.0 https://github.com/symfony/symfony/pull/49254
                if (str_contains($e->getMessage(), 'supports(): Argument #1')) {
                    continue;
                }

                throw $e;
            }

            // as soon as at least one attribute is supported, default is to deny access
            $vote = self::ACCESS_DENIED;

            if ($this->voteOnAttribute($attribute, $subject, $token)) {
                // grant access as soon as at least one attribute returns a positive response
                return self::ACCESS_GRANTED;
            }
        }

        return $vote;
    }

    /**
     * Return false if your voter doesn't support the given attribute. Symfony will cache
     * that decision and won't call your voter again for that attribute.
     */
    public function supportsAttribute(string $attribute): bool
    {
        return true;
    }

    /**
     * Return false if your voter doesn't support the given subject type. Symfony will cache
     * that decision and won't call your voter again for that subject type.
     *
     * @param string $subjectType The type of the subject inferred by `get_class()` or `get_debug_type()`
     */
    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param mixed $subject The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @psalm-assert-if-true TSubject $subject
     * @psalm-assert-if-true TAttribute $attribute
     */
    abstract protected function supports(string $attribute, mixed $subject): bool;

    /**
     * Perform a single access check operation on a given attribute, subject and token.
     * It is safe to assume that $attribute and $subject already passed the "supports()" method check.
     *
     * @phpstan-ignore-next-line string type will be fixed in Symfony 7.0
     *
     * @param TAttribute $attribute
     * @param TSubject   $subject
     */
    abstract protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool;
}
