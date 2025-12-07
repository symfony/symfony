<?php

namespace Symfony\Component\AccessControl;

use Symfony\Component\AccessControl\Event\AccessDecisionEvent;
use Symfony\Component\AccessControl\Event\VoteEvent;
use Symfony\Component\AccessControl\Exception\InvalidStrategyException;
use Symfony\Component\AccessControl\Strategy\AffirmativeStrategy;
use Symfony\Component\AccessControl\Strategy\StrategyInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental
 */
final class AccessControlManager implements AccessControlManagerInterface
{
    private readonly string $defaultStrategy;

    /**
     * @var array<string, StrategyInterface>
     */
    private readonly array $strategies;

    /**
     * @var array<string, array<array-key, bool>>
     */
    private array $votersCacheAttributes = [];
    /**
     * @var array<string, array<array-key, bool>>
     */
    private mixed $votersCacheSubject = [];

    /**
     * @param iterable<StrategyInterface> $strategies
     * @param iterable<VoterInterface> $voters
     */
    public function __construct(
        iterable $strategies,
        private readonly iterable $voters,
        ?string $defaultStrategy = null,
        private readonly ?EventDispatcherInterface $dispatcher = null
    ) {
        $namedStrategies = [];
        foreach ($strategies as $strategy) {
            $namedStrategies[$strategy->getName()] = $strategy;
        }
        if (\count($namedStrategies) === 0) {
            $namedStrategies['affirmative'] = new AffirmativeStrategy();
            $defaultStrategy = 'affirmative';
        }
        if ($defaultStrategy === null) {
            $defaultStrategy = array_key_first($namedStrategies);
            assert($defaultStrategy !== null, 'The default strategy cannot be null.');
        }
        $this->defaultStrategy = $defaultStrategy;
        $this->strategies = $namedStrategies;
    }

    public function decide(AccessRequest $accessRequest, ?string $strategy = null): AccessDecision
    {
        $strategy = $strategy ?? $this->defaultStrategy;
        if (!isset($this->strategies[$strategy])) {
            throw new InvalidStrategyException(sprintf('Strategy "%s" is not registered. Valid strategies are: %s', $strategy, implode(', ', array_keys($this->strategies))));
        }
        $votes = [];
        foreach ($this->getVoters($accessRequest) as $voter) {
            $vote = $voter->vote($accessRequest);
            $votes[] = $vote;
            $this->dispatcher?->dispatch(new VoteEvent($voter, $accessRequest, $vote));
        }

        $accessDecision = $this->strategies[$strategy]->evaluate($accessRequest, $votes);
        if ($accessDecision->decision !== DecisionVote::ACCESS_ABSTAIN) {
            $this->dispatcher?->dispatch(new AccessDecisionEvent($accessRequest, $accessDecision));
            return $accessDecision;
        }

        $accessDecision = AccessDecision::deny($accessRequest, $votes, $accessDecision->reason);
        if ($accessRequest->allowIfAllAbstainOrTie) {
            $accessDecision = AccessDecision::grant($accessRequest, $votes, $accessDecision->reason);
        }

        $this->dispatcher?->dispatch(new AccessDecisionEvent($accessRequest, $accessDecision));

        return $accessDecision;
    }

    /**
     * @return iterable<VoterInterface>
     */
    private function getVoters(AccessRequest $accessRequest): iterable
    {
        $keyAttribute = \is_object($accessRequest->attribute) ? $accessRequest->attribute::class : get_debug_type($accessRequest->attribute);
        $keySubject = \is_object($accessRequest->subject) ? $accessRequest->subject::class : get_debug_type($accessRequest->subject);
        foreach ($this->voters as $key => $voter) {
            if (!isset($this->votersCacheAttributes[$keyAttribute][$key])) {
                $this->votersCacheAttributes[$keyAttribute][$key] = $voter->supportsAttribute($accessRequest->attribute);
            }
            if (!$this->votersCacheAttributes[$keyAttribute][$key]) {
                continue;
            }

            if (!isset($this->votersCacheSubject[$keySubject][$key])) {
                $this->votersCacheSubject[$keySubject][$key] = $voter->supportsSubject($accessRequest->subject);
            }
            if (!$this->votersCacheSubject[$keySubject][$key]) {
                continue;
            }
            yield $voter;
        }
    }
}
