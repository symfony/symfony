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

    public function __construct(array $config = array())
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(array(
            'host' => null,
            'port' => 389,
            'version' => 3,
            'useSsl' => false,
            'useStartTls' => false,
            'optReferrals' => false,
        ));
        $resolver->setNormalizer('host', function (Options $options, $value) {
            if ($value && $options['useSsl']) {
                return 'ldaps://'.$value;
            }

            return $value;
        });

        $this->config = $resolver->resolve($config);
    }
}
