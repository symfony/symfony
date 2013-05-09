<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\Collection;
use Symfony\Component\Cache\Data\FreshItem;
use Symfony\Component\Cache\Data\ValidItem;
use Symfony\Component\Cache\Data\CollectionInterface;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\ItemInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class MetadataExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'metadata_pattern' => '%s.__metadata__',
            'with_metadata'    => true,
        ))->addAllowedTypes(array(
            'metadata_pattern' => 'string',
            'with_metadata'    => 'boolean',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult(DataInterface $data, Cache $cache, array $options = array())
    {
        if (!$options['with_metadata']) {
            return $data;
        }

        $pattern = $options['metadata_suffix'];

        if ($data instanceof ValidItem) {
            $metadata = $cache->fetch(array('id' => sprintf($pattern, $data->getKey())));
            /** @var ItemInterface $metadata */
            if ($metadata->isValid()) {
                $data->metadata = $metadata->getData();
            }
        }

        if ($data instanceof CollectionInterface) {
            $metadata = $cache->fetch(array('id' => array_map(function ($key) use ($pattern) {
                return sprintf($pattern, $key);
            }, $data->getKeys())));
            /** @var CollectionInterface $metadata */
            if ($metadata->isValid()) {
                foreach ($metadata->all() as $metadataKey => $metadataItem) {
                    $item = $data->get(preg_replace('~^'.str_replace('%s', '(.*)', $pattern).'$~', '$1', $metadataKey));
                    if ($item instanceof ValidItem) {
                        $item->metadata = $metadataItem->getData();
                    }
                }
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, Cache $cache, array $options = array())
    {
        if (!$options['with_metadata']) {
            return $data;
        }

        $suffix = $options['metadata_suffix'];

        if ($data instanceof ValidItem) {
            return new Collection(array($data, new FreshItem($data->getKey().$suffix, $data->metadata)));
        }

        if ($data instanceof CollectionInterface) {
            return $data->merge(new Collection(array_map(function (ValidItem $item) use ($suffix) {
                return new FreshItem($item->getKey().$suffix, $item->metadata);
            }, $data->all())));
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function propagateDeletion(KeyCollection $keys, Cache $cache, array $options = array())
    {
        if (!$options['with_metadata']) {
            return $keys;
        }

        $suffix = $options['metadata_suffix'];

        return $keys->merge(new KeyCollection(array_map(function ($key) use ($suffix) {
            return $key.$suffix;
        }, $keys->getKeys())));
    }
}
