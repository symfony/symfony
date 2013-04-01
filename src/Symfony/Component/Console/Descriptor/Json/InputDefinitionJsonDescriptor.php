<?php

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class InputDefinitionJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getData($object)
    {
        $argumentDescriptor = $this->build(new InputArgumentJsonDescriptor());
        $optionDescriptor = $this->build(new InputOptionJsonDescriptor());

        /** @var InputDefinition $object */
        return array(
            'arguments' => array_map(function (InputArgument $argument) use ($argumentDescriptor) {
                return $argumentDescriptor->getData($argument);
            }, $object->getArguments()),
            'options' => array_map(function (InputOption $option) use ($optionDescriptor) {
                return $optionDescriptor->getData($option);
            }, $object->getOptions()),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof InputDefinition;
    }
}
