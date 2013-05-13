<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;
use Symfony\Component\Cache\Data\ValidItem;
use Symfony\Component\Cache\Exception\LockException;
use Symfony\Component\Cache\Lock\Lock;
use Symfony\Component\Cache\Lock\LockFactory;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class TagExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'tags_metadata_key' => 'tags',
            'tags_pattern'      => '__tags__.%s',
        ))->addAllowedTypes(array(
            'tags_metadata_key' => 'string',
            'tags_pattern'      => 'string',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function supportsQuery(array $query, array $options)
    {
        return array('tag') === array_keys($query);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveQuery(array $query, array $options)
    {
        $item = $this->findTag($query['tag'], $options);

        if ($item instanceof CachedItem) {
            return new KeyCollection($item->getValue());
        }

        return new NullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, array $options)
    {
        if ($data instanceof ValidItem) {
            $this->registerItem($data, $options);
        }

        if ($data instanceof Collection) {
            foreach ($data->all() as $item) {
                if ($item instanceof ValidItem) {
                    $this->registerItem($item, $options);
                }
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveRemoval(array $query, array $options)
    {
        $data = $this->getCache()->get($query, array('with_metadata' => true));

        if ($data instanceof ValidItem) {
            return $this->unregisterItem($data, $options);
        }

        $keys = new KeyCollection();

        if ($data instanceof Collection) {
            foreach ($data->all() as $item) {
                if ($item instanceof ValidItem) {
                    $keys->merge($this->unregisterItem($item, $options));
                }
            }
        }

        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'tag';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredExtensions()
    {
        return array('metadata', 'lock');
    }

    /**
     * @param ValidItem $item
     * @param array     $options
     *
     * @throws LockException
     */
    private function registerItem(ValidItem $item, array $options)
    {
        $tagKeys = $item->metadata->get($options['tags_metadata_key'], array());

        if (empty($tagKeys)) {
            return;
        }

        /** @var Lock $lock */
        $lock = $options['lock_factory']->create($tagKeys);

        if (!$lock->acquire($this->getCache())) {
            throw new LockException(sprintf('Could not acquire lock for "%s" keys.', implode('", "', $lock->getFreeKeys())));
        }

        $dataToStore = array();

        foreach ($tagKeys as $tagKey) {
            $tagItem = $this->findTag($tagKey, $options);
            $tagData = $tagItem instanceof CachedItem ? $tagItem->getValue() : array();

            if (!in_array($tagKey, $tagData)) {
                $tagData[] = $item->getKey();
                $dataToStore[] = new FreshItem(sprintf($options['tags_pattern'], $tagKey), $tagData);
            }
        }

        if (count($dataToStore)) {
            $this->getCache()->set(new Collection($dataToStore), $options);
        }

        if (!$lock->release($this->getCache())) {
            throw new LockException(sprintf('Could not release lock for "%s" keys.', implode('", "', $lock->getAcquiredKeys())));
        }
    }

    /**
     * @param ValidItem $item
     * @param array     $options
     *
     * @return KeyCollection
     *
     * @throws LockException
     */
    private function unregisterItem(ValidItem $item, array $options)
    {
        $tagKeys = $item->metadata->get($options['tags_metadata_key'], array());

        if (empty($tagKeys)) {
            return new KeyCollection();
        }

        /** @var Lock $lock */
        $lock = $options['lock_factory']->create($tagKeys);

        if (!$lock->acquire($this->getCache())) {
            throw new LockException(sprintf('Could not acquire lock for "%s" keys.', implode('", "', $lock->getFreeKeys())));
        }

        $dataToStore = array();
        $keysToDelete = new KeyCollection();

        foreach ($tagKeys as $tagKey) {
            $tagItem = $this->findTag($tagKey, $options);

            if ($tagItem instanceof CachedItem) {
                $tagData = $tagItem->getValue();

                if ($key = array_search($tagKey, $tagData)) {
                    unset($tagData[$key]);
                    $dataToStore[] = new FreshItem(sprintf($options['tags_pattern'], $tagKey), $tagData);
                }
            }
        }

        if (count($dataToStore)) {
            $this->getCache()->set(new Collection($dataToStore), $options);
        }

        if (!$lock->release($this->getCache())) {
            throw new LockException(sprintf('Could not release lock for "%s" keys.', implode('", "', $lock->getAcquiredKeys())));
        }

        return $keysToDelete;
    }

    /**
     * @param string $tag
     * @param array  $options
     *
     * @return CachedItem|NullResult
     */
    private function findTag($tag, array $options)
    {
        $options['with_metadata'] = false;

        return $this->getCache()->get(array('key' => sprintf($options['tags_pattern'], $tag)), $options);
    }
}
