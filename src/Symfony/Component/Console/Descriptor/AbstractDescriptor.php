<?php

namespace Symfony\Component\Console\Descriptor;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractDescriptor implements DescriptorInterface
{
    /**
     * @var DescriptorProvider|null
     */
    private $descriptorProvider;

    /**
     * {@inheritdoc}
     */
    public function setDescriptorProvider(DescriptorProvider $descriptorProvider)
    {
        $this->descriptorProvider = $descriptorProvider;
    }

    /**
     * Returns a descriptor for given object with current format.
     *
     * @param object $object
     *
     * @return DescriptorInterface
     *
     * @throws \LogicException
     */
    protected function getDescriptor($object)
    {
        if (null === $this->descriptorProvider) {
            throw new \LogicException('You must set a descriptor provider to get a descriptor.');
        }

        return $this->descriptorProvider->get($object, $this->getFormat());
    }
}
