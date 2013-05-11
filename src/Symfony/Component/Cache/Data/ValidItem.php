<?php

namespace Symfony\Component\Cache\Data;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class ValidItem implements ItemInterface
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var Metadata
     */
    public $metadata;

    /**
     * @param string $key
     * @param mixed  $value
     */
    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
        $this->metadata = new Metadata();
    }

    /**
     * @param ValidItem $item
     *
     * @return static
     */
    public static function createFromItem(ValidItem $item)
    {
        $createdItem = new static($item->getKey(), $item->getValue());
        $createdItem->metadata = $item->metadata;

        return $createdItem;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }
}
