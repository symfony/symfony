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
    private ?string $strategy = null;
    /** @var array<VoterInterface> */
    private array $voters = [];
    private array $decisionLog = []; // All decision logs
    private array $currentLog = [];  // Logs being filled in
    private array $accessDecisionStack = [];

    public function __construct(
        private AccessDecisionManagerInterface $manager,
    ) {
    }

    public function decide(TokenInterface $token, array $attributes, mixed $object = null, bool|AccessDecision|null $accessDecision = null, bool $allowMultipleAttributes = false): bool
    {
        if (\is_bool($accessDecision)) {
            $allowMultipleAttributes = $accessDecision;
            $accessDecision = null;
        }

        // Using a stack since decide can be called by voters
        $this->currentLog[] = [
            'attributes' => $attributes,
            'object' => $object,
            'voterDetails' => [],
        ];

        $accessDecision ??= end($this->accessDecisionStack) ?: new AccessDecision();
        $this->accessDecisionStack[] = $accessDecision;

        try {
            return $accessDecision->isGranted = $this->manager->decide($token, $attributes, $object, $accessDecision, $allowMultipleAttributes);
        } finally {
            $this->strategy = $accessDecision->strategy;
            $currentLog = array_pop($this->currentLog);
            if (isset($accessDecision->isGranted)) {
                $currentLog['result'] = $accessDecision->isGranted;
            }
            $this->decisionLog[] = $currentLog;
        }
    }

    public function addVoterVote(VoterInterface $voter, array $attributes, int $vote, array $reasons = []): void
    {
        $currentLogIndex = \count($this->currentLog) - 1;
        $this->currentLog[$currentLogIndex]['voterDetails'][] = [
            'voter' => $voter,
            'attributes' => $attributes,
            'vote' => $vote,
            'reasons' => $reasons,
        ];
        $this->voters[$voter::class] = $voter;
    }

    public function getStrategy(): string
    {
        return $this->strategy ?? '-';
    }

    /**
     * @return array<VoterInterface>
     */
    public function getVoters(): array
    {
        return $this->voters;
    }

    public function getDecisionLog(): array
    {
        return $this->decisionLog;
    }

    public function reset(): void
    {
        $this->strategy = null;
        $this->voters = [];
        $this->decisionLog = [];
    }
}
