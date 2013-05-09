<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Rewriting;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class CoreExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'namespace' => '',
            'rewriting' => new Rewriting(),
        ))->addAllowedTypes(array(
            'namespace' => 'string',
            'rewriting' => 'Symfony\Component\Cache\Rewriting',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsQuery(array $query, array $options = array())
    {
        return array('key') === array_keys($query);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchResult(array $query, Cache $cache, array $options = array())
    {
        return $cache->getDriver()->fetch($this->getKey($query['key'], $options));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteData(array $query, Cache $cache, array $options = array())
    {
        return $cache->getDriver()->delete($this->getKey($query['key'], $options));
    }

    /**
     * @param string|array $query
     * @param array        $options
     *
     * @throws \InvalidArgumentException
     *
     * @return KeyCollection
     */
    private function getKey($query, array $options)
    {
        if (is_array($query)) {
            $keys = new KeyCollection();
            foreach ($query as $key) {
                $keys->merge($this->getKey($key, $options));
            }

            return $keys;
        }

        if (is_string($query)) {
            $query = $options['rewriting']->rewrite($query);
            $query = '' !== $options['namespace'] ? $options['namespace'].'.'.$query : $query;

            return new KeyCollection(array($query));
        }

        throw new \InvalidArgumentException('ID query must be string or array.');
    }
}
