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

use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;
use Symfony\Component\Cache\Data\ValidItem;
use Symfony\Component\Cache\Extension\Lock\LockFactory;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Lock extension.
 *
 * This extension introduces items locking:
 * * introduces a `lock_factory` option used to lock/unlock items
 * * prevents from setting/removing locked items
 *
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
            'lock_timeout' => 50,
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
    public function prepareStorage(DataInterface $data, array $options)
    {
        if ($data instanceof Collection) {
            $unlockedKeys = $this->filterUnlockedKeys($data->getKeys(), $options);
            $collection = new Collection();

            foreach ($unlockedKeys as $unlockedKey) {
                $collection->add($data->get($unlockedKey));
            }

            return $collection;
        }

        if ($data instanceof ValidItem && 0 === count($this->filterUnlockedKeys(array($data->getKey()), $options))) {
            return new NullResult();
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareRemoval(KeyCollection $keys, array $options)
    {
        return new KeyCollection($this->filterUnlockedKeys($keys->getKeys(), $options));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'lock';
    }

    /**
     * @param string[] $keys
     * @param array    $options
     *
     * @return string[]
     */
    private function filterUnlockedKeys(array $keys, array $options)
    {
        /** @var LockFactory $factory */
        $factory = $options['lock_factory'];

        foreach ($keys as $index => $key) {
            if (!$factory->create(array($key))->test($this->getCache())) {
                unset($keys[$index]);
            }
        }

        return $keys;
    }
}
