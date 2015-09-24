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

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Abstract Voter implementation that reduces boilerplate code required to create a custom Voter.
 *
 * @author Roman Marintšenko <inoryy@gmail.com>
 */
abstract class AbstractVoter implements VoterInterface
{
    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        return in_array($attribute, $this->getSupportedAttributes());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        foreach ($this->getSupportedClasses() as $supportedClass) {
            if ($supportedClass === $class || is_subclass_of($class, $supportedClass)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Iteratively check all given attributes by calling isGranted.
     *
     * This method terminates as soon as it is able to return ACCESS_GRANTED
     * If at least one attribute is supported, but access not granted, then ACCESS_DENIED is returned
     * Otherwise it will return ACCESS_ABSTAIN
     *
     * @param TokenInterface $token      A TokenInterface instance
     * @param object         $object     The object to secure
     * @param array          $attributes An array of attributes associated with the method being invoked
     *
     * @return int either ACCESS_GRANTED, ACCESS_ABSTAIN, or ACCESS_DENIED
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        if (!$object || !$this->supportsClass(get_class($object))) {
            return self::ACCESS_ABSTAIN;
        }

        // abstain vote by default in case none of the attributes are supported
        $vote = self::ACCESS_ABSTAIN;

        $reflector = new \ReflectionMethod($this, 'voteOnAttribute');
        $isNewOverwritten = $reflector->getDeclaringClass()->getName() !== 'Symfony\Component\Security\Core\Authorization\Voter\AbstractVoter';
        if (!$isNewOverwritten) {
            @trigger_error(sprintf("The AbstractVoter::isGranted method is deprecated since 2.8 and won't be called anymore in 3.0. Override voteOnAttribute() instead.", $reflector->class), E_USER_DEPRECATED);
        }

        foreach ($attributes as $attribute) {
            if (!$this->supportsAttribute($attribute)) {
                continue;
            }

            // as soon as at least one attribute is supported, default is to deny access
            $vote = self::ACCESS_DENIED;

            if ($isNewOverwritten) {
                if ($this->voteOnAttribute($attribute, $object, $token)) {
                    // grant access as soon as at least one voter returns a positive response
                    return self::ACCESS_GRANTED;
                }
            } else {
                if ($this->isGranted($attribute, $object, $token->getUser())) {
                    // grant access as soon as at least one voter returns a positive response
                    return self::ACCESS_GRANTED;
                }
            }
        }

        return $vote;
    }

    /**
     * Return an array of supported classes. This will be called by supportsClass.
     *
     * @return array an array of supported classes, i.e. array('Acme\DemoBundle\Model\Product')
     */
    abstract protected function getSupportedClasses();

    /**
     * Return an array of supported attributes. This will be called by supportsAttribute.
     *
     * @return array an array of supported attributes, i.e. array('CREATE', 'READ')
     */
    abstract protected function getSupportedAttributes();

    /**
     * Perform a single access check operation on a given attribute, object and (optionally) user
     * It is safe to assume that $attribute and $object's class pass supportsAttribute/supportsClass
     * $user can be one of the following:
     *   a UserInterface object (fully authenticated user)
     *   a string               (anonymously authenticated user).
     *
     * @param string               $attribute
     * @param object               $object
     * @param UserInterface|string $user
     *
     * @deprecated This method will be removed in 3.0 - override voteOnAttribute instead.
     *
     * @return bool
     */
    protected function isGranted($attribute, $object, $user = null)
    {
        return false;
    }

    /**
     * Perform a single access check operation on a given attribute, object and (optionally) user
     * It is safe to assume that $attribute and $object's class pass supportsAttribute/supportsClass
     * $user can be one of the following:
     *   a UserInterface object (fully authenticated user)
     *   a string               (anonymously authenticated user).
     *
     * This method will become abstract in 3.0.
     *
     * @param string         $attribute
     * @param object         $object
     * @param TokenInterface $token
     *
     * @return bool
     */
    protected function voteOnAttribute($attribute, $object, TokenInterface $token)
    {
        return false;
    }
}
