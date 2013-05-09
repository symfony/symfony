<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ExtensionStack implements ExtensionInterface
{
    /**
     * @var array
     */
    private $extensions = array();

    /**
     * @var boolean
     */
    private $sorted = true;

    /**
     * @param string             $name
     * @param ExtensionInterface $extension
     * @param int                $priority
     *
     * @return ExtensionStack
     */
    public function register($name, ExtensionInterface $extension, $priority = 0)
    {
        $this->sorted = false;
        $this->extensions[$name] = array(
            'index'     => count($this->extensions),
            'extension' => $extension,
            'priority'  => $priority,
        );

        return $this;
    }

    /**
     * @param string $name
     *
     * @return ExtensionInterface
     *
     * @throws \InvalidArgumentException
     */
    public function get($name)
    {
        if (!isset($this->extensions[$name])) {
            throw new \InvalidArgumentException('Extension not found.');
        }

        return $this->extensions[$name]['extension'];
    }

    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolverInterface $resolver)
    {
        foreach ($this->all() as $extension) {
            $extension->configure($resolver);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsQuery(array $query, array $options = array())
    {
        foreach ($this->all() as $extension) {
            if ($extension->supportsQuery($query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchResult(array $query, Cache $cache, array $options = array())
    {
        return $this->find($query)->fetchResult($query, $cache, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult(DataInterface $data, Cache $cache, array $options = array())
    {
        foreach ($this->all() as $extension) {
            $data = $extension->buildResult($data, $cache, $options);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, Cache $cache, array $options = array())
    {
        /** @var ExtensionInterface $extension */
        foreach (array_reverse($this->all()) as $extension) {
            $data = $extension->prepareStorage($data, $cache, $options);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteData(array $query, Cache $cache, array $options = array())
    {
        $this->sort();

        return $this->find($query)->deleteData($query, $cache, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function propagateDeletion(KeyCollection $keys, Cache $cache, array $options = array())
    {
        foreach ($this->all() as $extension) {
            $keys->merge($extension->propagateDeletion($keys, $cache, $options));
        }

        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareFlush(Cache $cache, array $options = array())
    {
        foreach ($this->all() as $extension) {
            $extension->prepareFlush($cache, $options);
        }
    }

    /**
     * @return ExtensionInterface[]
     */
    public function all()
    {
        $this->sort();

        return array_map(function (array $extension) {
            return $extension['extension'];
        }, $this->extensions);
    }

    /**
     * @param string $query
     *
     * @return ExtensionInterface
     *
     * @throws \InvalidArgumentException
     */
    private function find($query)
    {
        foreach ($this->all() as $extension) {
            if ($extension->suportsQuery($query)) {
                return $extension;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unsupported query "%s".', $query));
    }

    private function sort()
    {
        if ($this->sorted) {
            return;
        }

        uksort($this->extensions, function (array $a, array $b) {
            return $a['priority'] === $b['priority']
                ? ($a['index'] < $b['index'] ? 1 : -1)
                : $a['priority'] > $b['priority'] ? 1 : -1;
        });
    }
}
