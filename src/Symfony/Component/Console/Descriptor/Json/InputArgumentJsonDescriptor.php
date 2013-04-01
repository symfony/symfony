<?php

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Input\InputArgument;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputArgumentJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getData($object)
    {
        /** @var InputArgument $object */
        return array(
            'name'        => $object->getName(),
            'is_required' => $object->isRequired(),
            'is_array'    => $object->isArray(),
            'description' => $object->getDescription(),
            'default'     => $object->getDefault(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputArgument;
    }
}
