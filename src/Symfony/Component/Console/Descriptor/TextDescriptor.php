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
 * Text descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class TextDescriptor extends Descriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeInputArgument(InputArgument $argument, array $options = array())
    {
        if (null !== $argument->getDefault() && (!is_array($argument->getDefault()) || count($argument->getDefault()))) {
            $default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($argument->getDefault()));
        } else {
            $default = '';
        }

        $nameWidth = isset($options['name_width']) ? $options['name_width'] : strlen($argument->getName());
        $output = str_replace("\n", "\n".str_repeat(' ', $nameWidth + 2), $argument->getDescription());
        $output = sprintf(" <info>%-${nameWidth}s</info> %s%s", $argument->getName(), $output, $default);

        return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeInputOption(InputOption $option, array $options = array())
    {
        if ($option->acceptValue() && null !== $option->getDefault() && (!is_array($option->getDefault()) || count($option->getDefault()))) {
            $default = sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($option->getDefault()));
        } else {
            $default = '';
        }

        $nameWidth = isset($options['name_width']) ? $options['name_width'] : strlen($option->getName());
        $nameWithShortcutWidth = $nameWidth - strlen($option->getName()) - 2;

        $output = sprintf(" <info>%s</info> %-${nameWithShortcutWidth}s%s%s%s",
            '--'.$option->getName(),
            $option->getShortcut() ? sprintf('(-%s) ', $option->getShortcut()) : '',
            str_replace("\n", "\n".str_repeat(' ', $nameWidth + 2), $option->getDescription()),
            $default,
            $option->isArray() ? '<comment> (multiple values allowed)</comment>' : ''
        );

        return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
        $nameWidth = 0;
        foreach ($definition->getOptions() as $option) {
            $nameLength = strlen($option->getName()) + 2;
            if ($option->getShortcut()) {
                $nameLength += strlen($option->getShortcut()) + 3;
            }
            $nameWidth = max($nameWidth, $nameLength);
        }
        foreach ($definition->getArguments() as $argument) {
            $nameWidth = max($nameWidth, strlen($argument->getName()));
        }
        ++$nameWidth;

        $messages = array();

        if ($definition->getArguments()) {
            $messages[] = '<comment>Arguments:</comment>';
            foreach ($definition->getArguments() as $argument) {
                $messages[] = $this->describeInputArgument($argument, array('name_width' => $nameWidth));
            }
            $messages[] = '';
        }

        if ($definition->getOptions()) {
            $messages[] = '<comment>Options:</comment>';
            foreach ($definition->getOptions() as $option) {
                $messages[] = $this->describeInputOption($option, array('name_width' => $nameWidth));
            }
            $messages[] = '';
        }

        $output = implode("\n", $messages);

        return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeCommand(Command $command, array $options = array())
    {
        $command->getSynopsis();
        $command->mergeApplicationDefinition(false);
        $messages = array('<comment>Usage:</comment>', ' '.$command->getSynopsis(), '');

        if ($command->getAliases()) {
            $messages[] = '<comment>Aliases:</comment> <info>'.implode(', ', $command->getAliases()).'</info>';
        }

        $messages[] = $this->describeInputDefinition($command->getNativeDefinition());

        if ($help = $command->getProcessedHelp()) {
            $messages[] = '<comment>Help:</comment>';
            $messages[] = ' '.str_replace("\n", "\n ", $help)."\n";
        }

        $output = implode("\n", $messages);

        return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
    }

    /**
     * {@inheritdoc}
     */
    protected function describeApplication(Application $application, array $options = array())
    {
        $describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
        $description = new ApplicationDescription($application, $describedNamespace);
        $messages = array();

        if (isset($options['raw_text']) && $options['raw_text']) {
            $width = $this->getColumnWidth($description->getCommands());

            foreach ($description->getCommands() as $command) {
                $messages[] = sprintf("%-${width}s %s", $command->getName(), $command->getDescription());
            }
        } else {
            $width = $this->getColumnWidth($description->getCommands());

            $messages[] = $application->getHelp();
            $messages[] = '';

            if ($describedNamespace) {
                $messages[] = sprintf("<comment>Available commands for the \"%s\" namespace:</comment>", $describedNamespace);
            } else {
                $messages[] = '<comment>Available commands:</comment>';
            }

            // add commands by namespace
            foreach ($description->getNamespaces() as $namespace) {
                if (!$describedNamespace && ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
                    $messages[] = '<comment>'.$namespace['id'].'</comment>';
                }

                foreach ($namespace['commands'] as $name) {
                    $messages[] = sprintf("  <info>%-${width}s</info> %s", $name, $description->getCommand($name)->getDescription());
                }
            }
        }

        $output = implode("\n", $messages);

        return isset($options['raw_text']) && $options['raw_text'] ? strip_tags($output) : $output;
    }

    /**
     * Formats input option/argument default value.
     *
     * @param mixed $default
     *
     * @return string
     */
    private function formatDefaultValue($default)
    {
        if (version_compare(PHP_VERSION, '5.4', '<')) {
            return str_replace('\/', '/', json_encode($default));
        }

        return json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param Command[] $commands
     *
     * @return int
     */
    private function getColumnWidth(array $commands)
    {
        $width = 0;
        foreach ($commands as $command) {
            $width = strlen($command->getName()) > $width ? strlen($command->getName()) : $width;
        }

        return $width + 2;
    }
}
