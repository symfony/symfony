<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\CachedItem;
use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\FreshItem;
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
    public function supportsQuery(array $query, array $options = array())
    {
        return array('tag') === array_keys($query);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchResult(array $query, Cache $cache, array $options = array())
    {
        $item = $this->findTag($cache, $query['tag'], $options);

        if ($item instanceof CachedItem) {
            return $cache->fetch(array('id' => $item->getData()), $options);
        }

        return new NullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, Cache $cache, array $options = array())
    {
        if ($data instanceof ValidItem) {
            $this->registerItem($cache, $data, $options);
        }

        if ($data instanceof Collection) {
            foreach ($data->all() as $item) {
                if ($item instanceof ValidItem) {
                    $this->registerItem($cache, $item, $options);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteData(array $query, Cache $cache, array $options = array())
    {
        $data = $cache->fetch($query, array('with_metadata' => true));

        if ($data instanceof ValidItem) {
            $this->deregisterItem($cache, $data, $options);
        }

        if ($data instanceof Collection) {
            foreach ($data->all() as $item) {
                if ($item instanceof ValidItem) {
                    $this->deregisterItem($cache, $item, $options);
                }
            }
        }
    }

    /**
     * @param Cache     $cache
     * @param ValidItem $item
     * @param array     $options
     */
    private function registerItem(Cache $cache, ValidItem $item, array $options)
    {
        $dataToStore = array();

        foreach ($item->metadata->get($options['tags_metadata_key'], array()) as $tag) {
            $tagItem = $this->findTag($cache, $tag, $options);
            $tagData = $tagItem instanceof CachedItem ? $tagItem->getData() : array();

            if (!in_array($tag, $tagData)) {
                $tagsData[] = $item->getKey();
                $dataToStore[] = new FreshItem(sprintf($options['tags_pattern'], $tag), $tagData);
            }
        }

        if (count($dataToStore)) {
            $cache->store(new Collection($dataToStore), $options);
        }
    }

    /**
     * @param Cache     $cache
     * @param ValidItem $item
     * @param array     $options
     */
    private function deregisterItem(Cache $cache, ValidItem $item, array $options)
    {
        $dataToStore = array();

        foreach ($item->metadata->get($options['tags_metadata_key'], array()) as $tag) {
            $tagItem = $this->findTag($cache, $tag, $options);

            if ($tagItem instanceof CachedItem) {
                $tagData = $tagItem->getData();

                if ($key = array_search($tag, $tagData)) {
                    unset($tagData[$key]);
                    $dataToStore[] = new FreshItem(sprintf($options['tags_pattern'], $tag), $tagData);
                }
            }
        }

        if (count($dataToStore)) {
            $cache->store(new Collection($dataToStore), $options);
        }
    }

    /**
     * @param Cache  $cache
     * @param string $tag
     * @param array  $options
     *
     * @return CachedItem|NullResult
     */
    private function findTag(Cache $cache, $tag, array $options)
    {
        if (empty($tags)) {
            return new NullResult();
        }

        $options['with_metadata'] = false;

        return $cache->fetch(array('id' => sprintf($options['tags_pattern'], $tag)), $options);
    }
}
