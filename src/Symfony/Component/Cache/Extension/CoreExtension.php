<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Rewriting;
use Symfony\Component\OptionsResolver\Options;
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
            'rewriting' => null,
        ))->addAllowedTypes(array(
            'namespace' => 'string',
            'rewriting' => 'Symfony\Component\Cache\Rewriting',
        ))->setNormalizers(array(
            'rewriting' => function ($value) {
                return $value instanceof Rewriting ? $value : new Rewriting();
            },
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsQuery(array $query, array $options)
    {
        return array('key') === array_keys($query);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveQuery(array $query, array $options)
    {
        return $this->getKey($query['key'], $options);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveRemoval(array $query, array $options)
    {
        return $this->getKey($query['key'], $options);
    }

    /**
     * @param string|array $query
     * @param array        $options
     *
     * @throws InvalidArgumentException
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
            $query = $options['namespace'] ? $options['namespace'].'.'.$query : $query;

            return new KeyCollection(array($query));
        }

        throw new InvalidArgumentException(sprintf('Query must be string or array, "%s" given.', var_export($query, true)));
    }
}
