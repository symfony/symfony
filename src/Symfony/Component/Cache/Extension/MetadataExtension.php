<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Data\CachedItem;
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
            'with_metadata'    => 'bool',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult(DataInterface $data, array $options)
    {
        if (!$options['with_metadata']) {
            return $data;
        }

        $pattern = $options['metadata_pattern'];

        if ($data instanceof ValidItem) {
            $metadata = $this->getCache()->get(array('key' => sprintf($pattern, $data->getKey())));
            /** @var ItemInterface $metadata */
            if ($metadata instanceof CachedItem) {
                $data->metadata = $metadata->getValue();
            }
        }

        if ($data instanceof CollectionInterface) {
            $metadata = $this->getCache()->get(array('key' => array_map(function ($key) use ($pattern) {
                return sprintf($pattern, $key);
            }, $data->getKeys())));
            /** @var CollectionInterface $metadata */
            if ($metadata instanceof Collection) {
                /** @var ItemInterface $metadataItem */
                foreach ($metadata->all() as $metadataKey => $metadataItem) {
                    $item = $data->get(preg_replace('~^'.str_replace('%s', '(.*)', preg_quote($pattern, '~')).'$~', '$1', $metadataKey));
                    if ($item instanceof CachedItem) {
                        $item->metadata = $metadataItem->getValue();
                    }
                }
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, array $options)
    {
        if (!$options['with_metadata']) {
            return $data;
        }

        $pattern = $options['metadata_pattern'];
        $options['with_metadata'] = false;

        if ($data instanceof ValidItem && !$data->metadata->isEmpty()) {
            $this->getCache()->set(new Collection(array($data, new FreshItem(sprintf($pattern, $data->getKey()), $data->metadata))), $options);
        }

        if ($data instanceof CollectionInterface) {
            $this->getCache()->set(new Collection(array_map(function (ValidItem $item) use ($pattern) {
                return new FreshItem(sprintf($pattern, $item->getKey()), $item->metadata);
            }, array_filter($data->all(), function (ItemInterface $item) {
                return $item instanceof ValidItem && !$item->metadata->isEmpty();
            }))), $options);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function propagateRemoval(KeyCollection $keys, array $options)
    {
        if (!$options['with_metadata']) {
            return $keys;
        }

        $pattern = $options['metadata_pattern'];

        return $keys->merge(new KeyCollection(array_map(function ($key) use ($pattern) {
            return sprintf($pattern, $key);
        }, $keys->getKeys())));
    }
}
