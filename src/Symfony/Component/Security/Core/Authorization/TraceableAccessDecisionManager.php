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
    private $manager;
    private $strategy;
    private $voters = [];
    private $decisionLog = [];

    public function __construct(AccessDecisionManagerInterface $manager)
    {
        $this->manager = $manager;

        if ($this->manager instanceof AccessDecisionManager) {
            // The strategy and voters are stored in a private properties of the decorated service
            $reflection = new \ReflectionProperty(AccessDecisionManager::class, 'strategy');
            $reflection->setAccessible(true);
            $this->strategy = $reflection->getValue($manager);
            $reflection = new \ReflectionProperty(AccessDecisionManager::class, 'voters');
            $reflection->setAccessible(true);
            $this->voters = $reflection->getValue($manager);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $result = $this->manager->decide($token, $attributes, $object);

        $this->decisionLog[] = [
            'attributes' => $attributes,
            'object' => $object,
            'result' => $result,
        ];

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated since version 3.3, to be removed in 4.0. Pass voters to the decorated AccessDecisionManager instead.
     */
    public function setVoters(array $voters)
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Pass voters to the decorated AccessDecisionManager instead.', __METHOD__), \E_USER_DEPRECATED);

        if (!method_exists($this->manager, 'setVoters')) {
            return;
        }

        $this->voters = $voters;
        $this->manager->setVoters($voters);
    }

    /**
     * @return string
     */
    public function getStrategy()
    {
        // The $strategy property is misleading because it stores the name of its
        // method (e.g. 'decideAffirmative') instead of the original strategy name
        // (e.g. 'affirmative')
        return null === $this->strategy ? '-' : strtolower(substr($this->strategy, 6));
    }

    /**
     * @return iterable|VoterInterface[]
     */
    public function getVoters()
    {
        return $this->voters;
    }

    /**
     * @return array
     */
    public function getDecisionLog()
    {
        return $this->decisionLog;
    }
}

class_alias(TraceableAccessDecisionManager::class, DebugAccessDecisionManager::class);
