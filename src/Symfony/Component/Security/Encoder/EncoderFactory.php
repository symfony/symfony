<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Encoder;

use Symfony\Component\Security\User\AccountInterface;

/**
 * A generic encoder factory implementation
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EncoderFactory implements EncoderFactoryInterface
{
    protected $encoders;
    protected $encoderMap;

    public function __construct(array $encoderMap)
    {
        $this->encoders = array();
        $this->encoderMap = $encoderMap;
    }

    /**
     * {@inheritDoc}
     */
    public function getEncoder(AccountInterface $account)
    {
        foreach ($this->encoders as $class => $encoder) {
            if ($account instanceof $class) {
                return $encoder;
            }
        }

        return $this->createEncoder($account);
    }

    /**
     * Adds an encoder instance to the factory
     *
     * @param string $class
     * @param PasswordEncoderInterface $encoder
     * @return void
     */
    public function addEncoder($class, PasswordEncoderInterface $encoder)
    {
        $this->encoders[$class] = $encoder;
    }

    /**
     * Creates the actual encoder instance
     *
     * @param AccountInterface $account
     * @return PasswordEncoderInterface
     */
    protected function createEncoder($account)
    {
        foreach ($this->encoderMap as $class => $config) {
            if ($account instanceof $class) {
                $reflection = new \ReflectionClass($config['class']);
                $this->encoders[$class] = $reflection->newInstanceArgs($config['arguments']);

                return $this->encoders[$class];
            }
        }

        throw new \InvalidArgumentException(sprintf('No encoder has been configured for account "%s".', get_class($account)));
    }
}