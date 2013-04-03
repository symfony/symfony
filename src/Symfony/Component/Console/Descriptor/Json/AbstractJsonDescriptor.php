<?php

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Descriptor\DescriptorInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractJsonDescriptor implements DescriptorInterface
{
    /**
     * @var int
     */
    private $encodingOptions;

    /**
     * @param int $encodingOptions
     */
    public function __construct($encodingOptions = 0)
    {
        $this->encodingOptions = $encodingOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        $this->encodingOptions = $options['json_encoding'];

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function describe($object, $raw = false)
    {
        return json_encode($this->getData($object), $this->encodingOptions);
    }

    /**
     * Returns object data to encode.
     *
     * @param object $object
     *
     * @return array
     */
    abstract public function getData($object);

    /**
     * {@inheritdoc}
     */
    public function getFormat()
    {
        return 'json';
    }

    /**
     * {@inheritdoc}
     */
    public function useFormatting()
    {
        return false;
    }

    /**
     * Builds a JSON descriptor.
     *
     * @param AbstractJsonDescriptor $descriptor
     *
     * @return AbstractJsonDescriptor
     */
    protected function build(AbstractJsonDescriptor $descriptor)
    {
        $descriptor->configure(array('json_encoding' => $this->encodingOptions));

        return $descriptor;
    }
}
