<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;
use Symfony\Component\Cache\Data\ValidItem;
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
    public function resolveFetch(array $query, array $options)
    {
        $item = $this->findTag($query['tag'], $options);

        if ($item instanceof CachedItem) {
            return new KeyCollection($item->getData());
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
    public function resolveDeletion(array $query, array $options)
    {
        $data = $this->getCache()->fetch($query, array('with_metadata' => true));

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
     * @param ValidItem $item
     * @param array     $options
     */
    private function registerItem(ValidItem $item, array $options)
    {
        $dataToStore = array();

        foreach ($item->metadata->get($options['tags_metadata_key'], array()) as $tag) {
            $tagItem = $this->findTag($tag, $options);
            $tagData = $tagItem instanceof CachedItem ? $tagItem->getData() : array();

            if (!in_array($tag, $tagData)) {
                $tagData[] = $item->getKey();
                $dataToStore[] = new FreshItem(sprintf($options['tags_pattern'], $tag), $tagData);
            }
        }

        if (count($dataToStore)) {
            $this->getCache()->store(new Collection($dataToStore), $options);
        }
    }

    /**
     * @param ValidItem $item
     * @param array     $options
     *
     * @return KeyCollection
     */
    private function unregisterItem(ValidItem $item, array $options)
    {
        $dataToStore = array();
        $keysToDelete = new KeyCollection();

        foreach ($item->metadata->get($options['tags_metadata_key'], array()) as $tag) {
            $tagItem = $this->findTag($tag, $options);

            if ($tagItem instanceof CachedItem) {
                $tagData = $tagItem->getData();

                if ($key = array_search($tag, $tagData)) {
                    unset($tagData[$key]);
                    $dataToStore[] = new FreshItem(sprintf($options['tags_pattern'], $tag), $tagData);
                }
            }
        }

        if (count($dataToStore)) {
            $this->getCache()->store(new Collection($dataToStore), $options);
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

        return $this->getCache()->fetch(array('key' => sprintf($options['tags_pattern'], $tag)), $options);
    }
}
