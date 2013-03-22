<?php

namespace Symfony\Component\Console\Descriptor;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class DescriptorProvider
{
    /**
     * @var DescriptorInterface[]
     */
    private $descriptors = array();

    /**
     * @var string
     */
    private $defaultFormat;

    /**
     * Constructor.
     *
     * @param string $defaultFormat
     */
    public function __construct($defaultFormat = 'txt')
    {
        $this->defaultFormat = $defaultFormat;
    }

    /**
     * Adds a descriptor to the stack.
     *
     * @param DescriptorInterface $descriptor
     *
     * @return DescriptorProvider
     */
    public function add(DescriptorInterface $descriptor)
    {
        $this->descriptors[] = $descriptor;

        return $this;
    }

    /**
     * Provides a descriptor for given object and format.
     *
     * @param mixed  $object The object to describe
     * @param string $format The description format
     *
     * @return DescriptorInterface The object descriptor
     *
     * @throws \InvalidArgumentException If no descriptors was found
     */
    public function get($object, $format)
    {
        foreach ($this->descriptors as $descriptor) {
            if ($format === $descriptor->getFormat() && $descriptor->supports($object)) {
                return $descriptor;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unsupported format "%s".', $format));
    }

    /**
     * Returns default format.
     *
     * @return string
     */
    public function getDefaultFormat()
    {
        return $this->defaultFormat;
    }

    /**
     * Returns supported formats list.
     *
     * @return array
     */
    public function getSupportedFormats()
    {
        $supportedFormats = array();
        foreach ($this->descriptors as $descriptor) {
            $supportedFormats[] = $descriptor->getFormat();
        }

        return array_unique($supportedFormats);
    }
}
