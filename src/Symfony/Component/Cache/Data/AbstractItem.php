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
     * @var string
     */
    private $data;

    /**
     * @var Metadata
     */
    public $metadata;

    /**
     * @param string $key
     * @param mixed  $data
     */
    public function __construct($key, $data)
    {
        $this->key = $key;
        $this->data = $data;
        $this->metadata = new Metadata();
    }

    /**
     * @param ValidItem $item
     *
     * @return static
     */
    public static function duplicate(ValidItem $item)
    {
        $duplicate = new static($item->getKey(), $item->getData());
        $duplicate->metadata = $item->metadata;

        return $duplicate;
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
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isCollection()
    {
        return false;
    }
}
