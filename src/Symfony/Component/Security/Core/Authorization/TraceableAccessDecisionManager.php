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
    private $defaultStrategy;
    private $voters = array();
    private $decisionLog = array();

    public function __construct(AccessDecisionManagerInterface $manager)
    {
        $this->manager = $manager;

        if ($this->manager instanceof AccessDecisionManager) {
            // The default strategy is stored in a private property of the decorated service
            $reflection = new \ReflectionProperty(AccessDecisionManager::class, 'defaultStrategy');
            $reflection->setAccessible(true);
            $this->defaultStrategy = $reflection->getValue($manager);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function decide(TokenInterface $token, array $attributes, $object = null)
    {
        $result = $this->manager->decide($token, $attributes, $object);

        $strategy = 'unknown';
        if ($this->manager instanceof AccessDecisionManager) {
            $strategy = $this->manager->getStrategy($token, $attributes, $object);
        }

        $this->decisionLog[] = array(
            'attributes' => $attributes,
            'object' => $object,
            'result' => $result,
            'strategy' => $strategy,
        );

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setVoters(array $voters)
    {
        if (!method_exists($this->manager, 'setVoters')) {
            return;
        }

        $this->voters = $voters;
        $this->manager->setVoters($voters);
    }

    /**
     * @return string
     */
    public function getDefaultStrategy()
    {
        return null === $this->defaultStrategy ? 'unknown' : $this->defaultStrategy;
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

class_alias(TraceableAccessDecisionManager::class, DebugAccessDecisionManager::class);
