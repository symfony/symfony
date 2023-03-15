<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
abstract class AbstractConnection implements ConnectionInterface
{
    protected $config;

    public function __construct(array $config = [])
    {
        $resolver = new OptionsResolver();

        $this->configureOptions($resolver);

        $this->config = $resolver->resolve($config);
    }

    /**
     * @return void
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'host' => 'localhost',
            'version' => 3,
            'connection_string' => null,
            'encryption' => 'none',
            'options' => [],
        ]);

        $resolver->setDefault('port', fn (Options $options) => 'ssl' === $options['encryption'] ? 636 : 389);

        $resolver->setDefault('connection_string', fn (Options $options) => sprintf('ldap%s://%s:%s', 'ssl' === $options['encryption'] ? 's' : '', $options['host'], $options['port']));

        $resolver->setAllowedTypes('host', 'string');
        $resolver->setAllowedTypes('port', 'numeric');
        $resolver->setAllowedTypes('connection_string', 'string');
        $resolver->setAllowedTypes('version', 'numeric');
        $resolver->setAllowedValues('encryption', ['none', 'ssl', 'tls']);
        $resolver->setAllowedTypes('options', 'array');
    }
}
