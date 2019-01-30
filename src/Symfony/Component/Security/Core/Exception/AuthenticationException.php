<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Exception;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AuthenticationException is the base class for all authentication exceptions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander <iam.asm89@gmail.com>
 */
class AuthenticationException extends RuntimeException implements \Serializable
{
    private $token;

    /**
     * Get the token.
     *
     * @return TokenInterface
     */
    public function getToken()
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token)
    {
        $this->token = $token;
    }

    /**
     * {@inheritdoc}
     *
     * @final since Symfony 4.3, use getState() instead
     *
     * @internal since Symfony 4.3, use getState() instead
     */
    public function serialize()
    {
        return $this->doSerialize($this->getState(), \func_num_args() ? \func_get_arg(0) : null);
    }

    /**
     * {@inheritdoc}
     *
     * @final since Symfony 4.3, use setState() instead
     *
     * @internal since Symfony 4.3, use setState() instead
     */
    public function unserialize($serialized)
    {
        $this->setState(\is_array($serialized) ? $serialized : unserialize($serialized));
    }

    /**
     * Returns all the necessary state of the object for serialization purposes.
     *
     * There is no need to serialize any entry, they should be returned as-is.
     * If you extend this method, keep in mind you MUST guarantee parent data is present in the state.
     * Here is an example of how to extend this method:
     * <code>
     *     protected function getState(): array
     *     {
     *         return [$this->childAttribute, parent::getState()];
     *     }
     * </code>
     *
     * @see setState()
     */
    protected function getState(): array
    {
        return [$this->token, $this->code, $this->message, $this->file, $this->line];
    }

    /**
     * Restores the object state from an array given by getState().
     *
     * There is no need to unserialize any entry in $data, they are already ready-to-use.
     * If you extend this method, keep in mind you MUST pass the parent data to its respective class.
     * Here is an example of how to extend this method:
     * <code>
     *     protected function setState(array $data)
     *     {
     *         [$this->childAttribute, $parentData] = $data;
     *         parent::setState($parentData);
     *     }
     * </code>
     *
     * @see getState()
     */
    protected function setState(array $data)
    {
        [$this->token, $this->code, $this->message, $this->file, $this->line] = $data;
    }

    /**
     * @internal
     */
    protected function doSerialize($serialized, $isCalledFromOverridingMethod)
    {
        if (null === $isCalledFromOverridingMethod) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
            $isCalledFromOverridingMethod = isset($trace[2]['function'], $trace[2]['object']) && 'serialize' === $trace[2]['function'] && $this === $trace[2]['object'];
        }

        return $isCalledFromOverridingMethod ? $serialized : serialize($serialized);
    }

    /**
     * Message key to be used by the translation component.
     *
     * @return string
     */
    public function getMessageKey()
    {
        return 'An authentication exception occurred.';
    }

    /**
     * Message data to be used by the translation component.
     *
     * @return array
     */
    public function getMessageData()
    {
        return [];
    }
}
