<?php

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Abstract Voter implementation that reduces boilerplate code required to create a custom Voter
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
     * Iteratively check all given attributes by calling voteOnAttribute
     * This method terminates as soon as it is able to return either ACCESS_GRANTED or ACCESS_DENIED vote
     * Otherwise it will return ACCESS_ABSTAIN
     *
     * @param TokenInterface $token      A TokenInterface instance
     * @param object         $object     The object to secure
     * @param array          $attributes An array of attributes associated with the method being invoked
     *
     * @return int     either ACCESS_GRANTED, ACCESS_ABSTAIN, or ACCESS_DENIED
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        if (!$this->supportsClass(get_class($object))) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        $user = $token->getUser();

        foreach ($attributes as $attribute) {
            if ($this->supportsAttribute($attribute)) {
                $vote = $this->voteOnAttribute($attribute, $object, $user);
                if (VoterInterface::ACCESS_ABSTAIN !== $vote) {
                    return $vote;
                }
            }
        }

        return VoterInterface::ACCESS_ABSTAIN;
    }

    /**
     * Return an array of supported classes. This will be called by supportsClass
     *
     * @return array    an array of supported classes, i.e. ['\Acme\DemoBundle\Model\Product']
     */
    abstract protected function getSupportedClasses();

    /**
     * Return an array of supported attributes. This will be called by supportsAttribute
     *
     * @return array    an array of supported attributes, i.e. ['CREATE', 'READ']
     */
    abstract protected function getSupportedAttributes();

    /**
     * Perform a single vote operation on a given attribute, object and (optionally) user
     * It is safe to assume that $attribute and $object's class pass supportsAttribute/supportsClass
     *
     * @param string        $attribute
     * @param object        $object
     * @param UserInterface $user
     *
     * @return int     either ACCESS_GRANTED, ACCESS_ABSTAIN, or ACCESS_DENIED
     */
    abstract protected function voteOnAttribute($attribute, $object, UserInterface $user = null);
}