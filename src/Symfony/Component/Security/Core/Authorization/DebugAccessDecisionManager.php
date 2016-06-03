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

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Decorates the original AccessDecisionManager class to log information
 * about the security voters and the decisions made by them.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @internal
 */
class DebugAccessDecisionManager implements AccessDecisionManagerInterface
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
            'attributes' => $attributes,
            'object' => $this->getStringRepresentation($object),
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
        return strtolower(substr($this->strategy, 6));
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

    /**
     * @param mixed $object
     *
     * @return string
     */
    private function getStringRepresentation($object)
    {
        if (null === $object) {
            return 'NULL';
        }

        if (!is_object($object)) {
            if (is_bool($object)) {
                return sprintf('%s (%s)', gettype($object), $object ? 'true' : 'false');
            }
            if (is_scalar($object)) {
                return sprintf('%s (%s)', gettype($object), $object);
            }

            return gettype($object);
        }

        $objectClass = class_exists('Doctrine\Common\Util\ClassUtils') ? ClassUtils::getClass($object) : get_class($object);

        if (method_exists($object, 'getId')) {
            $objectAsString = sprintf('ID: %s', $object->getId());
        } elseif (method_exists($object, '__toString')) {
            $objectAsString = (string) $object;
        } else {
            $objectAsString = sprintf('object hash: %s', spl_object_hash($object));
        }

        return sprintf('%s (%s)', $objectClass, $objectAsString);
    }
}
