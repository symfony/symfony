<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Secrets;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\EnvNotFoundException;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class SecretEnvVarProcessor implements EnvVarProcessorInterface
{
    private $vault;
    private $localVault;

    public function __construct(AbstractVault $vault, AbstractVault $localVault = null)
    {
        $this->vault = $vault;
        $this->localVault = $localVault;
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
    public function getEnv($prefix, $name, \Closure $getEnv): string
    {
        if (null !== $this->localVault && null !== ($secret = $this->localVault->reveal($name)) && \array_key_exists($name, $this->vault->list())) {
            return $secret;
        }

        if (null !== $secret = $this->vault->reveal($name)) {
            return $secret;
        }

        throw new EnvNotFoundException($this->vault->getLastMessage() ?? sprintf('Secret "%s" not found or decryption key is missing.', $name));
    }
}
