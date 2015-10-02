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
        @trigger_error('The '.__METHOD__.' is deprecated since version 2.8 and will be removed in version 3.0.', E_USER_DEPRECATED);

        return in_array($attribute, $this->getSupportedAttributes());
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        @trigger_error('The '.__METHOD__.' is deprecated since version 2.8 and will be removed in version 3.0.', E_USER_DEPRECATED);

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
        if (!$object) {
            return self::ACCESS_ABSTAIN;
        }

        // abstain vote by default in case none of the attributes are supported
        $vote = self::ACCESS_ABSTAIN;
        $class = get_class($object);

        foreach ($attributes as $attribute) {
            if (!$this->supports($attribute, $class)) {
                continue;
            }

            // as soon as at least one attribute is supported, default is to deny access
            $vote = self::ACCESS_DENIED;

            if ($this->voteOnAttribute($attribute, $object, $token)) {
                // grant access as soon as at least one voter returns a positive response
                return self::ACCESS_GRANTED;
            }
        }

        return $vote;
    }

    /**
     * Determines if the attribute and class are supported by this voter.
     *
     * To determine if the passed class is instance of the supported class, the
     * isClassInstanceOf() method can be used.
     *
     * This method will become abstract in 3.0.
     *
     * @param string $attribute An attribute
     * @param string $class     The fully qualified class name of the passed object
     *
     * @return bool True if the attribute and class is supported, false otherwise
     */
    protected function supports($attribute, $class)
    {
        @trigger_error('The getSupportedClasses and getSupportedAttributes methods are deprecated since version 2.8 and will be removed in version 3.0. Overwrite supports instead.', E_USER_DEPRECATED);

        $classIsSupported = false;
        foreach ($this->getSupportedClasses() as $supportedClass) {
            if ($this->isClassInstanceOf($class, $supportedClass)) {
                $classIsSupported = true;
                break;
            }
        }

        if (!$classIsSupported) {
            return false;
        }

        if (!in_array($attribute, $this->getSupportedAttributes())) {
            return false;
        }

        return true;
    }

    /**
     * A helper method to test if the actual class is instanceof or equal
     * to the expected class.
     *
     * @param string $actualClass   The actual class name
     * @param string $expectedClass The expected class name
     *
     * @return bool
     */
    protected function isClassInstanceOf($actualClass, $expectedClass)
    {
        return $expectedClass === $actualClass || is_subclass_of($actualClass, $expectedClass);
    }

    /**
     * Return an array of supported classes. This will be called by supportsClass.
     *
     * @return array an array of supported classes, i.e. array('Acme\DemoBundle\Model\Product')
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use supports() instead.
     */
    protected function getSupportedClasses()
    {
        @trigger_error('The '.__METHOD__.' is deprecated since version 2.8 and will be removed in version 3.0.', E_USER_DEPRECATED);
    }

    /**
     * Return an array of supported attributes. This will be called by supportsAttribute.
     *
     * @return array an array of supported attributes, i.e. array('CREATE', 'READ')
     *
     * @deprecated since version 2.8, to be removed in 3.0. Use supports() instead.
     */
    protected function getSupportedAttributes()
    {
        @trigger_error('The '.__METHOD__.' is deprecated since version 2.8 and will be removed in version 3.0.', E_USER_DEPRECATED);
    }

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
        // forces isGranted() or voteOnAttribute() to be overridden
        throw new \BadMethodCallException(sprintf('You must override the voteOnAttribute() method in "%s".', get_class($this)));
    }

    /**
     * Perform a single access check operation on a given attribute, object and token.
     * It is safe to assume that $attribute and $object's class pass supports method call.
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
        // the user should override this method, and not rely on the deprecated isGranted()
        @trigger_error(sprintf("The AbstractVoter::isGranted() method is deprecated since 2.8 and won't be called anymore in 3.0. Override voteOnAttribute() in %s instead.", get_class($this)), E_USER_DEPRECATED);

        return $this->isGranted($attribute, $object, $token->getUser());
    }
}
