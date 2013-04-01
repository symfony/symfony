<?php

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Command\Command;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class CommandJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getData($object)
    {
        $definitionDescriptor = $this->build(new InputDefinitionJsonDescriptor());

        /** @var Command $object */
        return array(
            'name'        => $object->getName(),
            'usage'       => $object->getSynopsis(),
            'description' => $object->getDescription(),
            'help'        => $object->getProcessedHelp(),
            'aliases'     => $object->getAliases(),
            'definition'  => $definitionDescriptor->getData($object->getDefinition()),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Command;
    }
}
