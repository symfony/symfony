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

/**
 * AccessDecisionManager is the base class for all access decision managers
 * that use decision voters.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class DebugAccessDecisionManager implements AccessDecisionManagerInterface //extends AccessDecisionManager
{
    private $manager;
    private $strategy;
    private $voters;
    private $decisionLog = array();

    public function __construct(AccessDecisionManager $manager)
    {
        $this->manager = $manager;

        // The strategy is stored in a private property of the decorated service
        $reflection = new \ReflectionProperty($manager, 'strategy');
        $reflection->setAccessible(true);
        $this->strategy = $reflection->getValue($manager);
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $result = $this->manager->decide($token, $attributes, $object);

        $this->decisionLog[] = array(
            'token' => $token,
            'attributes' => $attributes,
            'object' => $object,
            'result' => $result,
        );

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setVoters(array $voters)
    {
        $this->voters = $voters;
    }

    /**
     * @return string
     */
    public function getStrategy()
    {
        // The $strategy property stores the name of its method, instead of the
        // original strategy name. Example:
        // method name = 'decideAffirmative', strategy = 'affirmative'
        $strategyName = strtolower(substr($this->strategy, 6));

        return $strategyName;
    }

    /**
     * @return array
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
