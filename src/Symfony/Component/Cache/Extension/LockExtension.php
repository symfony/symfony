<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Extension\Lock\LockFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class LockExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'lock_sleep'   => 1,
            'lock_timeout' => 500,
            'lock_pattern' => '%s.__lock__',
            'lock_factory' => function (Options $options) {
                return new LockFactory($options->get('lock_timeout'), $options->get('lock_sleep'), $options->get('lock_pattern'));
            }
        ))->addAllowedTypes(array(
            'lock_sleep'   => 'int',
            'lock_timeout' => 'int',
            'lock_pattern' => 'string',
            'lock_factory' => 'Symfony\Component\Cache\Extension\Lock\LockFactory',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'lock';
    }
}
