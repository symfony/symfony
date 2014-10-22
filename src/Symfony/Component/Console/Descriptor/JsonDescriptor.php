<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * JSON descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class JsonDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeInputArgument(InputArgument $argument, array $options = array())
    {
        return $this->output(array(
            'name' => $argument->getName(),
            'is_required' => $argument->isRequired(),
            'is_array' => $argument->isArray(),
            'description' => $argument->getDescription(),
            'default' => $argument->getDefault(),
        ), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeInputOption(InputOption $option, array $options = array())
    {
        return $this->output(array(
            'name' => '--'.$option->getName(),
            'shortcut' => $option->getShortcut() ? '-'.implode('|-', explode('|', $option->getShortcut())) : '',
            'accept_value' => $option->acceptValue(),
            'is_value_required' => $option->isValueRequired(),
            'is_multiple' => $option->isArray(),
            'description' => $option->getDescription(),
            'default' => $option->getDefault(),
        ), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
        $inputArguments = array();
        foreach ($definition->getArguments() as $name => $argument) {
            $inputArguments[$name] = $this->describeInputArgument($argument, array('as_array' => true));
        }

        $inputOptions = array();
        foreach ($definition->getOptions() as $name => $option) {
            $inputOptions[$name] = $this->describeInputOption($option, array('as_array' => true));
        }

        return $this->output(array('arguments' => $inputArguments, 'options' => $inputOptions), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCommand(Command $command, array $options = array())
    {
        $command->getSynopsis();
        $command->mergeApplicationDefinition(false);

        return $this->output(array(
            'name' => $command->getName(),
            'usage' => $command->getSynopsis(),
            'description' => $command->getDescription(),
            'help' => $command->getProcessedHelp(),
            'aliases' => $command->getAliases(),
            'definition' => $this->describeInputDefinition($command->getNativeDefinition(), array('as_array' => true)),
        ), $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeApplication(Application $application, array $options = array())
    {
        $describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
        $description = new ApplicationDescription($application, $describedNamespace);
        $commands = array();

        foreach ($description->getCommands() as $command) {
            $commands[] = $this->describeCommand($command, array('as_array' => true));
        }

        $data = $describedNamespace
            ? array('commands' => $commands, 'namespace' => $describedNamespace)
            : array('commands' => $commands, 'namespaces' => array_values($description->getNamespaces()));

        return $this->output($data, $options);
    }

    /**
     * Outputs data as array or string according to options.
     *
     * @param array $data
     * @param array $options
     *
     * @return array|string
     */
    private function output(array $data, array $options)
    {
        if (isset($options['as_array']) && $options['as_array']) {
            return $data;
        }

        return json_encode($data, isset($options['json_encoding']) ? $options['json_encoding'] : 0);
    }
}
