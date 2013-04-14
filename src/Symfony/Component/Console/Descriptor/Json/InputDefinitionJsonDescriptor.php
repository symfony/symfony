<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $arguments = array();
        /** @var InputDefinition $object */
        foreach ($object->getArguments() as $name => $argument) {
            $arguments[$name] = $this->getDescriptor($argument)->getData($argument);
        }

        $options = array();
        foreach ($object->getOptions() as $name => $option) {
            $options[$name] = $this->getDescriptor($option)->getData($option);
        }

        return array(
            'arguments' => $arguments,
            'options'   => $options
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
