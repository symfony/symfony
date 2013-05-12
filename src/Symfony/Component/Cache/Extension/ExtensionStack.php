<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Exception\InvalidQueryException;
use Symfony\Component\Cache\Exception\ObjectNotFoundException;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ExtensionStack implements ExtensionInterface
{
    /**
     * @var Cache|null
     */
    private $cache;

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

        if (null !== $this->cache) {
            $extension->setCache($this->cache);
        }

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
     * @throws ObjectNotFoundException
     */
    public function getExtension($name)
    {
        if (!isset($this->extensions[$name])) {
            throw new ObjectNotFoundException(sprintf(
                'Extension stack does not contain extension named "%s", present ones are "%s".',
                $name, implode('", "', array_keys($this->extensions))
            ));
        }

        return $this->extensions[$name]['extension'];
    }

    /**
     * {@inheritdoc}
     */
    public function setCache(Cache $cache)
    {
        foreach ($this->all() as $extension) {
            $extension->setCache($cache);
        }

        return $this;
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
    public function supportsQuery(array $query, array $options)
    {
        foreach ($this->all() as $extension) {
            if ($extension->supportsQuery($query, $options)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveQuery(array $query, array $options)
    {
        return $this
            ->find($query, $options)
            ->resolveQuery($query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult(DataInterface $data, array $options)
    {
        foreach ($this->all() as $extension) {
            $data = $extension->buildResult($data, $options);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, array $options)
    {
        /** @var ExtensionInterface $extension */
        foreach (array_reverse($this->all()) as $extension) {
            $data = $extension->prepareStorage($data, $options);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveRemoval(array $query, array $options)
    {
        return $this
            ->find($query, $options)
            ->resolveRemoval($query, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function propagateRemoval(KeyCollection $keys, array $options)
    {
        foreach ($this->all() as $extension) {
            $keys->merge($extension->propagateRemoval($keys, $options));
        }

        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareClear(array $options)
    {
        foreach ($this->all() as $extension) {
            $extension->prepareClear($options);
        }
    }

    /**
     * @return ExtensionInterface[]
     */
    public function all()
    {
        $this->sort();

        $extensions = array();
        foreach ($this->extensions as $extension) {
            $extensions[] = $extension['extension'];
        }

        return $extensions;
    }

    /**
     * @param string $query
     * @param array $options
     *
     * @throws InvalidQueryException
     *
     * @return ExtensionInterface
     */
    private function find($query, array $options)
    {
        foreach ($this->all() as $extension) {
            if ($extension->supportsQuery($query, $options)) {
                return $extension;
            }
        }

        throw InvalidQueryException::unsupported('None of the stacked extensions supports "%s" query.', $query);
    }

    private function sort()
    {
        if ($this->sorted) {
            return;
        }

        uasort($this->extensions, function (array $a, array $b) {
            return $a['priority'] === $b['priority']
                ? ($b['index'] - $a['index'])
                : $b['priority'] - $a['priority'];
        });
    }
}
