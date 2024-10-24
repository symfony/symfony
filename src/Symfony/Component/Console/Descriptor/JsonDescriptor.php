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
 *
 * @internal
 */
class JsonDescriptor extends Descriptor
{
    protected function describeInputArgument(InputArgument $argument, array $options = []): void
    {
        $this->writeData($this->getInputArgumentData($argument), $options);
    }

    protected function describeInputOption(InputOption $option, array $options = []): void
    {
        if ($this->skipHiddenOption($option, $options)) {
            return;
        }
        $this->writeData($this->getInputOptionData($option), $options);
        if ($option->isNegatable()) {
            $this->writeData($this->getInputOptionData($option, true), $options);
        }
    }

    protected function describeInputDefinition(InputDefinition $definition, array $options = []): void
    {
        $this->writeData($this->getInputDefinitionData($definition, $options), $options);
    }

    protected function describeCommand(Command $command, array $options = []): void
    {
        $this->writeData($this->getCommandData($command, $options), $options);
    }

    protected function describeApplication(Application $application, array $options = []): void
    {
        $describedNamespace = $options['namespace'] ?? null;
        $description = new ApplicationDescription($application, $describedNamespace, true);
        $commands = [];

        foreach ($description->getCommands() as $command) {
            $commands[] = $this->getCommandData($command, $options);
        }

        $data = [];
        if ('UNKNOWN' !== $application->getName()) {
            $data['application']['name'] = $application->getName();
            if ('UNKNOWN' !== $application->getVersion()) {
                $data['application']['version'] = $application->getVersion();
            }
        }

        $data['commands'] = $commands;

        if ($describedNamespace) {
            $data['namespace'] = $describedNamespace;
        } else {
            $data['namespaces'] = array_values($description->getNamespaces());
        }

        $this->writeData($data, $options);
    }

    /**
     * Writes data as json.
     */
    private function writeData(array $data, array $options): void
    {
        $flags = $options['json_encoding'] ?? 0;

        $this->write(json_encode($data, $flags));
    }

    private function getInputArgumentData(InputArgument $argument): array
    {
        return [
            'name' => $argument->getName(),
            'is_required' => $argument->isRequired(),
            'is_array' => $argument->isArray(),
            'description' => preg_replace('/\s*[\r\n]\s*/', ' ', $argument->getDescription()),
            'default' => \INF === $argument->getDefault() ? 'INF' : $argument->getDefault(),
        ];
    }

    private function getInputOptionData(InputOption $option, bool $negated = false): array
    {
        $data = $negated ? [
            'name' => '--no-'.$option->getName(),
            'shortcut' => '',
            'accept_value' => false,
            'is_value_required' => false,
            'is_multiple' => false,
            'is_deprecated' => $option->isDeprecated(),
            'description' => 'Negate the "--'.$option->getName().'" option',
            'default' => false,
        ] : [
            'name' => '--'.$option->getName(),
            'shortcut' => $option->getShortcut() ? '-'.str_replace('|', '|-', $option->getShortcut()) : '',
            'accept_value' => $option->acceptValue(),
            'is_value_required' => $option->isValueRequired(),
            'is_multiple' => $option->isArray(),
            'is_deprecated' => $option->isDeprecated(),
            'description' => preg_replace('/\s*[\r\n]\s*/', ' ', $option->getDescription()),
            'default' => \INF === $option->getDefault() ? 'INF' : $option->getDefault(),
        ];
        if (!$option->isDeprecated()) {
            unset($data['is_deprecated']);
        }

        return $data;
    }

    private function getInputDefinitionData(InputDefinition $definition, array $options): array
    {
        $inputArguments = [];
        foreach ($definition->getArguments() as $name => $argument) {
            $inputArguments[$name] = $this->getInputArgumentData($argument);
        }

        $inputOptions = [];
        foreach ($this->removeHiddenOptions($definition->getOptions(), $options) as $name => $option) {
            $inputOptions[$name] = $this->getInputOptionData($option);
            if ($option->isNegatable()) {
                $inputOptions['no-'.$name] = $this->getInputOptionData($option, true);
            }
        }

        return ['arguments' => $inputArguments, 'options' => $inputOptions];
    }

    private function getCommandData(Command $command, array $options = []): array
    {
        $short = $options['short'] ?? false;

        $data = [
            'name' => $command->getName(),
            'description' => $command->getDescription(),
        ];

        if ($short) {
            $data += [
                'usage' => $command->getAliases(),
            ];
        } else {
            $command->mergeApplicationDefinition(false);

            $data += [
                'usage' => array_merge([$command->getSynopsis()], $command->getUsages(), $command->getAliases()),
                'help' => $command->getProcessedHelp(),
                'definition' => $this->getInputDefinitionData($command->getDefinition(), $options),
            ];
        }

        $data['hidden'] = $command->isHidden();

        return $data;
    }
}
