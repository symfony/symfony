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
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * AccessDecisionManager is the base class for all access decision managers
 * that use decision voters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AccessDecisionManager implements AccessDecisionManagerInterface
{
    public const STRATEGY_AFFIRMATIVE = 'affirmative';
    public const STRATEGY_CONSENSUS = 'consensus';
    public const STRATEGY_UNANIMOUS = 'unanimous';
    public const STRATEGY_PRIORITY = 'priority';

    private $voters;
    private $strategy;
    private $allowIfAllAbstainDecisions;
    private $allowIfEqualGrantedDeniedDecisions;

    /**
     * @param iterable|VoterInterface[] $voters                             An array or an iterator of VoterInterface instances
     * @param string                    $strategy                           The vote strategy
     * @param bool                      $allowIfAllAbstainDecisions         Whether to grant access if all voters abstained or not
     * @param bool                      $allowIfEqualGrantedDeniedDecisions Whether to grant access if result are equals
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(iterable $voters = [], string $strategy = self::STRATEGY_AFFIRMATIVE, bool $allowIfAllAbstainDecisions = false, bool $allowIfEqualGrantedDeniedDecisions = true)
    {
        $strategyMethod = 'decide'.ucfirst($strategy);
        if ('' === $strategy || !\is_callable([$this, $strategyMethod])) {
            throw new \InvalidArgumentException(sprintf('The strategy "%s" is not supported.', $strategy));
        }

        $this->voters = $voters;
        $this->strategy = $strategyMethod;
        $this->allowIfAllAbstainDecisions = $allowIfAllAbstainDecisions;
        $this->allowIfEqualGrantedDeniedDecisions = $allowIfEqualGrantedDeniedDecisions;
    }

    /**
     * @param bool $allowMultipleAttributes Whether to allow passing multiple values to the $attributes array
     *
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null/*, bool $allowMultipleAttributes = false*/)
    {
        $allowMultipleAttributes = 3 < \func_num_args() && func_get_arg(3);

        // Special case for AccessListener, do not remove the right side of the condition before 6.0
        if (\count($attributes) > 1 && !$allowMultipleAttributes) {
            throw new InvalidArgumentException(sprintf('Passing more than one Security attribute to "%s()" is not supported.', __METHOD__));
        }

        return $this->{$this->strategy}($token, $attributes, $object);
    }

    /**
     * Grants access if any voter returns an affirmative response.
     *
     * If all voters abstained from voting, the decision will be based on the
     * allowIfAllAbstainDecisions property value (defaults to false).
     */
    private function decideAffirmative(TokenInterface $token, array $attributes, $object = null): AccessDecision
    {
        $votes = [];
        $deny = 0;
        foreach ($this->voters as $voter) {
            $votes[] = $vote = $this->vote($voter, $token, $object, $attributes);

            if ($vote->isGranted()) {
                return AccessDecision::createGranted($votes);
            }

            if ($vote->isDenied()) {
                ++$deny;
            } elseif (VoterInterface::ACCESS_ABSTAIN !== $result) {
                trigger_deprecation('symfony/security-core', '5.3', 'Returning "%s" in "%s::vote()" is deprecated, return one of "%s" constants: "ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN".', var_export($result, true), get_debug_type($voter), VoterInterface::class);
            }
        }

        if ($deny > 0) {
            return AccessDecision::createDenied($votes);
        }

        return $this->decideIfAllAbstainDecisions();
    }

    /**
     * Grants access if there is consensus of granted against denied responses.
     *
     * Consensus means majority-rule (ignoring abstains) rather than unanimous
     * agreement (ignoring abstains). If you require unanimity, see
     * UnanimousBased.
     *
     * If there were an equal number of grant and deny votes, the decision will
     * be based on the allowIfEqualGrantedDeniedDecisions property value
     * (defaults to true).
     *
     * If all voters abstained from voting, the decision will be based on the
     * allowIfAllAbstainDecisions property value (defaults to false).
     */
    private function decideConsensus(TokenInterface $token, array $attributes, $object = null): AccessDecision
    {
        $votes = [];
        $grant = 0;
        $deny = 0;
        foreach ($this->voters as $voter) {
            $votes[] = $vote = $this->vote($voter, $token, $object, $attributes);

            if ($vote->isGranted()) {
                ++$grant;
            } elseif ($vote->isDenied()) {
                ++$deny;
            } elseif (VoterInterface::ACCESS_ABSTAIN !== $result) {
                trigger_deprecation('symfony/security-core', '5.3', 'Returning "%s" in "%s::vote()" is deprecated, return one of "%s" constants: "ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN".', var_export($result, true), get_debug_type($voter), VoterInterface::class);
            }
        }

        if ($grant > $deny) {
            return AccessDecision::createGranted($votes);
        }

        if ($deny > $grant) {
            return AccessDecision::createDenied($votes);
        }

        if ($grant > 0) {
            return $this->allowIfEqualGrantedDeniedDecisions
                ? AccessDecision::createGranted()
                : AccessDecision::createDenied()
            ;
        }

        return $this->decideIfAllAbstainDecisions();
    }

    /**
     * Grants access if only grant (or abstain) votes were received.
     *
     * If all voters abstained from voting, the decision will be based on the
     * allowIfAllAbstainDecisions property value (defaults to false).
     */
    private function decideUnanimous(TokenInterface $token, array $attributes, $object = null): AccessDecision
    {
        $votes = [];
        $grant = 0;
        foreach ($this->voters as $voter) {
            foreach ($attributes as $attribute) {
                $votes[] = $vote = $this->vote($voter, $token, $object, [$attribute]);

                if ($vote->isDenied()) {
                    return AccessDecision::createDenied($votes);
                }

                if ($vote->isGranted()) {
                    ++$grant;
                } elseif (VoterInterface::ACCESS_ABSTAIN !== $result) {
                    trigger_deprecation('symfony/security-core', '5.3', 'Returning "%s" in "%s::vote()" is deprecated, return one of "%s" constants: "ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN".', var_export($result, true), get_debug_type($voter), VoterInterface::class);
                }
            }
        }

        // no deny votes
        if ($grant > 0) {
            return AccessDecision::createGranted($votes);
        }

        return $this->decideIfAllAbstainDecisions();
    }

    /**
     * Grant or deny access depending on the first voter that does not abstain.
     * The priority of voters can be used to overrule a decision.
     *
     * If all voters abstained from voting, the decision will be based on the
     * allowIfAllAbstainDecisions property value (defaults to false).
     */
    private function decidePriority(TokenInterface $token, array $attributes, $object = null)
    {
        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $object, $attributes);

            if (VoterInterface::ACCESS_GRANTED === $result) {
                return true;
            }

            if (VoterInterface::ACCESS_DENIED === $result) {
                return false;
            }

            if (VoterInterface::ACCESS_ABSTAIN !== $result) {
                trigger_deprecation('symfony/security-core', '5.3', 'Returning "%s" in "%s::vote()" is deprecated, return one of "%s" constants: "ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN".', var_export($result, true), get_debug_type($voter), VoterInterface::class);
            }
        }

        return $this->allowIfAllAbstainDecisions;
    }

    private function decideIfAllAbstainDecisions(): AccessDecision
    {
        return $this->allowIfAllAbstainDecisions
            ? AccessDecision::createGranted()
            : AccessDecision::createDenied()
        ;
    }

    private function vote(VoterInterface $voter, TokenInterface $token, $subject, array $attributes): Vote
    {
        if (\is_int($vote = $voter->vote($token, $subject, $attributes))) {
            trigger_deprecation('symfony/security', 5.1, 'Returning an int from the "%s::vote()" method is deprecated. Return a "%s" object instead.', \get_class($this->voter), Vote::class);
            $vote = Vote::create($vote);
        }

        return $vote;
    }
}
