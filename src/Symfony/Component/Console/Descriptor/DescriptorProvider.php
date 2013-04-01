<?php

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Descriptor\Json;

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
     * @var array
     */
    private $options = array(
        'default_format' => 'txt',
        'json_encoding'  => 0,
        'namespace'      => null,
    );

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this
            ->configure($options)
            ->add(new Json\ApplicationJsonDescriptor())
            ->add(new Json\CommandJsonDescriptor())
            ->add(new Json\InputDefinitionJsonDescriptor())
            ->add(new Json\InputArgumentJsonDescriptor())
            ->add(new Json\InputOptionJsonDescriptor())
        ;
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
        $descriptor->configure($this->options);
        $this->descriptors[] = $descriptor;

        return $this;
    }

    /**
     * Configures provider with options.
     *
     * @param array $options
     *
     * @return DescriptorProvider
     */
    public function configure(array $options)
    {
        $this->options = array_replace($this->options, $options);

        foreach ($this->descriptors as $descriptor) {
            $descriptor->configure($this->options);
        }

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
        return $this->options['default_format'];
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
