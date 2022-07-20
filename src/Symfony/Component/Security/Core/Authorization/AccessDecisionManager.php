<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\AccessDecisionStrategyInterface;
use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\PriorityStrategy;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * AccessDecisionManager is the base class for all access decision managers
 * that use decision voters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 5.4
 */
class AccessDecisionManager implements AccessDecisionManagerInterface
{
    /**
     * @deprecated use {@see AffirmativeStrategy} instead
     */
    public const STRATEGY_AFFIRMATIVE = 'affirmative';

    /**
     * @deprecated use {@see ConsensusStrategy} instead
     */
    public const STRATEGY_CONSENSUS = 'consensus';

    /**
     * @deprecated use {@see UnanimousStrategy} instead
     */
    public const STRATEGY_UNANIMOUS = 'unanimous';

    /**
     * @deprecated use {@see PriorityStrategy} instead
     */
    public const STRATEGY_PRIORITY = 'priority';

    private const VALID_VOTES = [
        VoterInterface::ACCESS_GRANTED => true,
        VoterInterface::ACCESS_DENIED => true,
        VoterInterface::ACCESS_ABSTAIN => true,
    ];

    private $voters;
    private $votersCacheAttributes;
    private $votersCacheObject;
    private $strategy;

    /**
     * @param iterable<mixed, VoterInterface>      $voters   An array or an iterator of VoterInterface instances
     * @param AccessDecisionStrategyInterface|null $strategy The vote strategy
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(iterable $voters = [], /* AccessDecisionStrategyInterface */ $strategy = null)
    {
        $this->voters = $voters;
        if (\is_string($strategy)) {
            trigger_deprecation('symfony/security-core', '5.4', 'Passing the access decision strategy as a string is deprecated, pass an instance of "%s" instead.', AccessDecisionStrategyInterface::class);
            $allowIfAllAbstainDecisions = 3 <= \func_num_args() && func_get_arg(2);
            $allowIfEqualGrantedDeniedDecisions = 4 > \func_num_args() || func_get_arg(3);

            $strategy = $this->createStrategy($strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);
        } elseif (null !== $strategy && !$strategy instanceof AccessDecisionStrategyInterface) {
            throw new \TypeError(sprintf('"%s": Parameter #2 ($strategy) is expected to be an instance of "%s" or null, "%s" given.', __METHOD__, AccessDecisionStrategyInterface::class, get_debug_type($strategy)));
        }

        $this->strategy = $strategy ?? new AffirmativeStrategy();
    }

    /**
     * @param bool $allowMultipleAttributes Whether to allow passing multiple values to the $attributes array
     *
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null/* , bool $allowMultipleAttributes = false */)
    {
        $allowMultipleAttributes = 3 < \func_num_args() && func_get_arg(3);

        // Special case for AccessListener, do not remove the right side of the condition before 6.0
        if (\count($attributes) > 1 && !$allowMultipleAttributes) {
            throw new InvalidArgumentException(sprintf('Passing more than one Security attribute to "%s()" is not supported.', __METHOD__));
        }

        return $this->strategy->decide(
            $this->collectResults($token, $attributes, $object)
        );
    }

    /**
     * @param mixed $object
     *
     * @return \Traversable<int, int>
     */
    private function collectResults(TokenInterface $token, array $attributes, $object): \Traversable
    {
        foreach ($this->getVoters($attributes, $object) as $voter) {
            $result = $voter->vote($token, $object, $attributes);
            if (!\is_int($result) || !(self::VALID_VOTES[$result] ?? false)) {
                trigger_deprecation('symfony/security-core', '5.3', 'Returning "%s" in "%s::vote()" is deprecated, return one of "%s" constants: "ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN".', var_export($result, true), get_debug_type($voter), VoterInterface::class);
            }

            yield $result;
        }
    }

    /**
     * @throws \InvalidArgumentException if the $strategy is invalid
     */
    private function createStrategy(string $strategy, bool $allowIfAllAbstainDecisions, bool $allowIfEqualGrantedDeniedDecisions): AccessDecisionStrategyInterface
    {
        switch ($strategy) {
            case self::STRATEGY_AFFIRMATIVE:
                return new AffirmativeStrategy($allowIfAllAbstainDecisions);
            case self::STRATEGY_CONSENSUS:
                return new ConsensusStrategy($allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);
            case self::STRATEGY_UNANIMOUS:
                return new UnanimousStrategy($allowIfAllAbstainDecisions);
            case self::STRATEGY_PRIORITY:
                return new PriorityStrategy($allowIfAllAbstainDecisions);
        }

        throw new \InvalidArgumentException(sprintf('The strategy "%s" is not supported.', $strategy));
    }

    /**
     * @return iterable<mixed, VoterInterface>
     */
    private function getVoters(array $attributes, $object = null): iterable
    {
        $keyAttributes = [];
        foreach ($attributes as $attribute) {
            $keyAttributes[] = \is_string($attribute) ? $attribute : null;
        }
        // use `get_class` to handle anonymous classes
        $keyObject = \is_object($object) ? \get_class($object) : get_debug_type($object);
        foreach ($this->voters as $key => $voter) {
            if (!$voter instanceof CacheableVoterInterface) {
                yield $voter;
                continue;
            }

            $supports = true;
            // The voter supports the attributes if it supports at least one attribute of the list
            foreach ($keyAttributes as $keyAttribute) {
                if (null === $keyAttribute) {
                    $supports = true;
                } elseif (!isset($this->votersCacheAttributes[$keyAttribute][$key])) {
                    $this->votersCacheAttributes[$keyAttribute][$key] = $supports = $voter->supportsAttribute($keyAttribute);
                } else {
                    $supports = $this->votersCacheAttributes[$keyAttribute][$key];
                }
                if ($supports) {
                    break;
                }
            }
            if (!$supports) {
                continue;
            }

            if (!isset($this->votersCacheObject[$keyObject][$key])) {
                $this->votersCacheObject[$keyObject][$key] = $supports = $voter->supportsType($keyObject);
            } else {
                $supports = $this->votersCacheObject[$keyObject][$key];
            }
            if (!$supports) {
                continue;
            }
            yield $voter;
        }
    }
}
