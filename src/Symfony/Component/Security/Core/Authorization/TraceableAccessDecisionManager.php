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
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Decorates the original AccessDecisionManager class to log information
 * about the security voters and the decisions made by them.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @internal
 */
class TraceableAccessDecisionManager implements AccessDecisionManagerInterface
{
    private AccessDecisionManagerInterface $manager;
    private ?AccessDecisionStrategyInterface $strategy = null;
    /** @var iterable<mixed, VoterInterface> */
    private iterable $voters = [];
    private array $decisionLog = []; // All decision logs
    private array $currentLog = [];  // Logs being filled in

    public function __construct(AccessDecisionManagerInterface $manager)
    {
        $this->manager = $manager;

        // The strategy and voters are stored in a private properties of the decorated service
        if (property_exists($manager, 'strategy')) {
            $reflection = new \ReflectionProperty(\get_class($manager), 'strategy');
            $this->strategy = $reflection->getValue($manager);
        }
        if (property_exists($manager, 'voters')) {
            $reflection = new \ReflectionProperty(\get_class($manager), 'voters');
            $this->voters = $reflection->getValue($manager);
        }
    }

    public function decide(TokenInterface $token, array $attributes, mixed $object = null, bool $allowMultipleAttributes = false): bool
    {
        $currentDecisionLog = [
            'attributes' => $attributes,
            'object' => $object,
            'voterDetails' => [],
        ];

        $this->currentLog[] = &$currentDecisionLog;

        $result = $this->manager->decide($token, $attributes, $object, $allowMultipleAttributes);

        $currentDecisionLog['result'] = $result;

        $this->decisionLog[] = array_pop($this->currentLog); // Using a stack since decide can be called by voters

        return $result;
    }

    /**
     * Adds voter vote and class to the voter details.
     *
     * @param array $attributes attributes used for the vote
     * @param int   $vote       vote of the voter
     */
    public function addVoterVote(VoterInterface $voter, array $attributes, int $vote)
    {
        $currentLogIndex = \count($this->currentLog) - 1;
        $this->currentLog[$currentLogIndex]['voterDetails'][] = [
            'voter' => $voter,
            'attributes' => $attributes,
            'vote' => $vote,
        ];
    }

    public function getStrategy(): string
    {
        if (null === $this->strategy) {
            return '-';
        }
        if (method_exists($this->strategy, '__toString')) {
            return (string) $this->strategy;
        }

        return get_debug_type($this->strategy);
    }

    /**
     * @return iterable<mixed, VoterInterface>
     */
    public function getVoters(): iterable
    {
        return $this->voters;
    }

    public function getDecisionLog(): array
    {
        return $this->decisionLog;
    }
}
