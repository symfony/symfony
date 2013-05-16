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
 * Metadata extension.
 *
 * This extension introduces item metadata:
 * * retrieves metadata attached to items
 * * stores metadata with item
 *
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
        $options['with_metadata'] = false;

        if ($data instanceof ValidItem) {
            $metadata = $this->getCache()->get(array('key' => sprintf($pattern, $data->getKey())), $options);
            /** @var ItemInterface $metadata */
            if ($metadata instanceof CachedItem) {
                $data->metadata = $metadata->getValue();
            }
        }

        if ($data instanceof CollectionInterface) {
            $metadata = $this->getCache()->get(array('key' => array_map(function ($key) use ($pattern) {
                return sprintf($pattern, $key);
            }, $data->getKeys())), $options);
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

        if ($data instanceof ValidItem && !$data->metadata->isEmpty()) {
            return new Collection(array($data, new FreshItem(sprintf($pattern, $data->getKey()), $data->metadata)));
        }

        if ($data instanceof CollectionInterface) {
            return $data->merge(new Collection(array_map(function (ValidItem $item) use ($pattern) {
                return new FreshItem(sprintf($pattern, $item->getKey()), $item->metadata);
            }, array_filter($data->all(), function (ItemInterface $item) {
                return $item instanceof ValidItem && !$item->metadata->isEmpty();
            }))));
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareRemoval(KeyCollection $keys, array $options)
    {
        if (!$options['with_metadata']) {
            return $keys;
        }

        $pattern = $options['metadata_pattern'];

        return $keys->merge(new KeyCollection(array_map(function ($key) use ($pattern) {
            return sprintf($pattern, $key);
        }, $keys->getKeys())));
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'metadata';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredExtensions()
    {
        return array('core');
    }
}
