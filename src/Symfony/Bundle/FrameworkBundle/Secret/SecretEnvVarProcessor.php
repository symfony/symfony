<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Secret;

use Symfony\Bundle\FrameworkBundle\Exception\SecretNotFoundException;
use Symfony\Bundle\FrameworkBundle\Secret\Storage\SecretStorageInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

/**
 * @author Tobias Schultze <http://tobion.de>
 */
class SecretEnvVarProcessor implements EnvVarProcessorInterface
{
    private $secretStorage;

    public function __construct(SecretStorageInterface $secretStorage)
    {
        $this->secretStorage = $secretStorage;
    }

    /**
     * {@inheritdoc}
     */
    public static function getProvidedTypes()
    {
        return [
            'secret' => 'string',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getEnv($prefix, $name, \Closure $getEnv)
    {
        try {
            return $this->secretStorage->getSecret($name);
        } catch (SecretNotFoundException $e) {
            throw new EnvNotFoundException($e->getMessage(), 0, $e);
        }
    }
}
