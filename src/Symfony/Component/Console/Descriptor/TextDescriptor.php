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
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\OutputWrapper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Terminal;

/**
 * Text descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @internal
 */
class TextDescriptor extends Descriptor
{
    private const MIN_DESCRIPTION_WIDTH = 20;
    private const FALLBACK_DESCRIPTION_INDENT = 6;

    protected function describeInputArgument(InputArgument $argument, array $options = []): void
    {
        if (null !== $argument->getDefault() && (!\is_array($argument->getDefault()) || \count($argument->getDefault()))) {
            $default = \sprintf('<comment> [default: %s]</comment>', $this->formatDefaultValue($argument->getDefault()));
        } else {
            $default = '';
        }

        $totalWidth = $options['total_width'] ?? Helper::width($argument->getName());
        $spacingWidth = $totalWidth - Helper::width($argument->getName());
        $terminalWidth = $this->getTerminalWidth($options);

        $description = preg_replace('/\s*[\r\n]\s*/', "\n", $argument->getDescription());
        $fullDescription = $description.$default;

        // + 4 = 2 spaces before <info>, 2 spaces after </info>
        $descriptionIndent = $totalWidth + 4;
        $availableWidth = $terminalWidth - $descriptionIndent;

        if ($availableWidth >= self::MIN_DESCRIPTION_WIDTH) {
            $this->writeText(\sprintf('  <info>%s</info>  %s%s',
                $argument->getName(),
                str_repeat(' ', $spacingWidth),
                $this->wrapText($fullDescription, $availableWidth, $descriptionIndent)
            ), $options);
        } else {
            $this->writeText(\sprintf("  <info>%s</info>\n%s%s",
                $argument->getName(),
                str_repeat(' ', self::FALLBACK_DESCRIPTION_INDENT),
                $this->wrapText($fullDescription, max(1, $terminalWidth - self::FALLBACK_DESCRIPTION_INDENT), self::FALLBACK_DESCRIPTION_INDENT)
            ), $options);
        }
    }

    protected function describeInputOption(InputOption $option, array $options = []): void
    {
        if ($option->acceptValue() && null !== $option->getDefault() && (!\is_array($option->getDefault()) || \count($option->getDefault()))) {
            $default = \sprintf('<comment> [default: %s]</comment>', $this->formatDefaultValue($option->getDefault()));
        } else {
            $default = '';
        }

        $value = '';
        if ($option->acceptValue()) {
            $value = '='.strtoupper($option->getName());

            if ($option->isValueOptional()) {
                $value = '['.$value.']';
            }
        }

        $totalWidth = $options['total_width'] ?? $this->calculateTotalWidthForOptions([$option]);
        $synopsis = \sprintf('%s%s',
            $option->getShortcut() ? \sprintf('-%s, ', $option->getShortcut()) : '    ',
            \sprintf($option->isNegatable() ? '--%1$s|--no-%1$s' : '--%1$s%2$s', $option->getName(), $value)
        );

        $spacingWidth = $totalWidth - Helper::width($synopsis);
        $terminalWidth = $this->getTerminalWidth($options);

        $synopsis = \sprintf('<%1$s>%2$s</%1$s>', $option->isDeprecated() ? 'fg=gray' : 'info', $synopsis);

        $prefix = ($option->isDeprecated() ? '[deprecated] ' : '').($option->isHidden() ? '[hidden] ' : '');
        $description = preg_replace('/\s*[\r\n]\s*/', "\n", trim($prefix.$option->getDescription()));
        $fullDescription = $description.$default.($option->isArray() ? '<comment> (multiple values allowed)</comment>' : '');

        // + 4 = 2 spaces before the synopsis, 2 spaces after it
        $descriptionIndent = $totalWidth + 4;
        $availableWidth = $terminalWidth - $descriptionIndent;

        if ($availableWidth >= self::MIN_DESCRIPTION_WIDTH) {
            $this->writeText(\sprintf('  %s  %s%s', $synopsis, str_repeat(' ', $spacingWidth), $this->wrapText($fullDescription, $availableWidth, $descriptionIndent)), $options);
        } else {
            $this->writeText(\sprintf("  %s\n%s%s", $synopsis, str_repeat(' ', self::FALLBACK_DESCRIPTION_INDENT), $this->wrapText($fullDescription, max(1, $terminalWidth - self::FALLBACK_DESCRIPTION_INDENT), self::FALLBACK_DESCRIPTION_INDENT)), $options);
        }
    }

    protected function describeInputDefinition(InputDefinition $definition, array $options = []): void
    {
        $inputArguments = $definition->getArguments();
        $inputOptions = $this->removeHiddenOptions($definition->getOptions(), $options);
        $totalWidth = $this->calculateTotalWidthForOptions($inputOptions);
        foreach ($inputArguments as $argument) {
            $totalWidth = max($totalWidth, Helper::width($argument->getName()));
        }

        if ($inputArguments) {
            $this->writeText('<comment>Arguments:</comment>', $options);
            $this->writeText("\n");
            foreach ($inputArguments as $argument) {
                $this->describeInputArgument($argument, array_merge($options, ['total_width' => $totalWidth]));
                $this->writeText("\n");
            }
        }

        if ($inputOptions) {
            if ($inputArguments) {
                $this->writeText("\n");
            }
            $laterOptions = [];

            $this->writeText('<comment>Options:</comment>', $options);
            foreach ($inputOptions as $option) {
                if (\strlen($option->getShortcut() ?? '') > 1) {
                    $laterOptions[] = $option;
                    continue;
                }
                $this->writeText("\n");
                $this->describeInputOption($option, array_merge($options, ['total_width' => $totalWidth]));
            }
            foreach ($laterOptions as $option) {
                $this->writeText("\n");
                $this->describeInputOption($option, array_merge($options, ['total_width' => $totalWidth]));
            }
        }
    }

    protected function describeCommand(Command $command, array $options = []): void
    {
        $command->mergeApplicationDefinition(false);
        $terminalWidth = $this->getTerminalWidth($options);

        if ($description = $command->getDescription()) {
            $this->writeText('<comment>Description:</comment>', $options);
            $this->writeText("\n");
            $this->writeText('  '.$this->wrapText($description, $terminalWidth - 2, 2));
            $this->writeText("\n\n");
        }

        $this->writeText('<comment>Usage:</comment>', $options);
        foreach (array_merge([$command->getSynopsis(true)], $command->getAliases(), $command->getUsages()) as $usage) {
            $this->writeText("\n");
            $this->writeText('  '.OutputFormatter::escape($usage), $options);
        }
        $this->writeText("\n");

        $definition = $command->getDefinition();
        if ($this->removeHiddenOptions($definition->getOptions(), $options) || $definition->getArguments()) {
            $this->writeText("\n");
            $this->describeInputDefinition($definition, $options);
            $this->writeText("\n");
        }

        $help = $command->getProcessedHelp();
        if ($help && $help !== $description) {
            $this->writeText("\n");
            $this->writeText('<comment>Help:</comment>', $options);
            $this->writeText("\n");
            $this->writeText('  '.$this->wrapText($help, $terminalWidth - 2, 2), $options);
            $this->writeText("\n");
        }
    }

    protected function describeApplication(Application $application, array $options = []): void
    {
        $describedNamespace = $options['namespace'] ?? null;
        $description = new ApplicationDescription($application, $describedNamespace);

        if (isset($options['raw_text']) && $options['raw_text']) {
            $width = $this->getColumnWidth($description->getCommands());

            foreach ($description->getCommands() as $command) {
                $this->writeText(\sprintf("%-{$width}s %s", $command->getName(), $command->getDescription()), $options);
                $this->writeText("\n");
            }
        } else {
            $terminalWidth = $this->getTerminalWidth($options);

            if ('' != $help = $application->getHelp()) {
                $this->writeText("$help\n\n", $options);
            }

            $this->writeText("<comment>Usage:</comment>\n", $options);
            $this->writeText("  command [options] [arguments]\n\n", $options);

            $this->describeInputDefinition(new InputDefinition($application->getDefinition()->getOptions()), $options);

            $this->writeText("\n");
            $this->writeText("\n");

            $commands = $description->getCommands();
            $namespaces = $description->getNamespaces();
            if ($describedNamespace && $namespaces) {
                // make sure all alias commands are included when describing a specific namespace
                $describedNamespaceInfo = reset($namespaces);
                foreach ($describedNamespaceInfo['commands'] as $name) {
                    $commands[$name] = $description->getCommand($name);
                }
            }

            // calculate max. width based on available commands per namespace
            $width = $this->getColumnWidth(array_merge(...array_values(array_map(static fn ($namespace) => array_intersect($namespace['commands'], array_keys($commands)), array_values($namespaces)))));

            if ($describedNamespace) {
                $this->writeText(\sprintf('<comment>Available commands for the "%s" namespace:</comment>', $describedNamespace), $options);
            } else {
                $this->writeText('<comment>Available commands:</comment>', $options);
            }

            foreach ($namespaces as $namespace) {
                $namespace['commands'] = array_filter($namespace['commands'], static fn ($name) => isset($commands[$name]));

                if (!$namespace['commands']) {
                    continue;
                }

                if (!$describedNamespace && ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
                    $this->writeText("\n");
                    $this->writeText(' <comment>'.$namespace['id'].'</comment>', $options);
                }

                foreach ($namespace['commands'] as $name) {
                    $this->writeText("\n");
                    $spacingWidth = $width - Helper::width($name);
                    $command = $commands[$name];
                    $commandAliases = $name === $command->getName() ? $this->getCommandAliasesText($command) : '';
                    $cmdDescription = $commandAliases.$command->getDescription();
                    $descriptionIndent = $width + 2;
                    $availableWidth = $terminalWidth - $descriptionIndent;

                    if ($availableWidth > 0) {
                        $cmdDescription = $this->wrapText($cmdDescription, $availableWidth, $descriptionIndent);
                    }

                    $this->writeText(\sprintf('  <info>%s</info>%s%s', $name, str_repeat(' ', $spacingWidth), $cmdDescription), $options);
                }
            }

            $this->writeText("\n");
        }
    }

    private function writeText(string $content, array $options = []): void
    {
        $this->write(
            isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content,
            isset($options['raw_output']) ? !$options['raw_output'] : true
        );
    }

    /**
     * Formats command aliases to show them in the command description.
     */
    private function getCommandAliasesText(Command $command): string
    {
        $text = '';
        $aliases = $command->getAliases();

        if ($aliases) {
            $text = '['.implode('|', $aliases).'] ';
        }

        return $text;
    }

    /**
     * Formats input option/argument default value.
     */
    private function formatDefaultValue(mixed $default): string
    {
        if (\INF === $default) {
            return 'INF';
        }

        if (\is_string($default)) {
            $default = OutputFormatter::escape($default);
        } elseif (\is_array($default)) {
            foreach ($default as $key => $value) {
                if (\is_string($value)) {
                    $default[$key] = OutputFormatter::escape($value);
                }
            }
        }

        return str_replace('\\\\', '\\', json_encode($default, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<Command|string> $commands
     */
    private function getColumnWidth(array $commands): int
    {
        $widths = [];

        foreach ($commands as $command) {
            if ($command instanceof Command) {
                $widths[] = Helper::width($command->getName());
                foreach ($command->getAliases() as $alias) {
                    $widths[] = Helper::width($alias);
                }
            } else {
                $widths[] = Helper::width($command);
            }
        }

        return $widths ? max($widths) + 2 : 0;
    }

    /**
     * @param InputOption[] $options
     */
    private function calculateTotalWidthForOptions(array $options): int
    {
        $totalWidth = 0;
        foreach ($options as $option) {
            // "-" + shortcut + ", --" + name
            $nameLength = 1 + max(Helper::width($option->getShortcut()), 1) + 4 + Helper::width($option->getName());
            if ($option->isNegatable()) {
                $nameLength += 6 + Helper::width($option->getName()); // |--no- + name
            } elseif ($option->acceptValue()) {
                $valueLength = 1 + Helper::width($option->getName()); // = + value
                $valueLength += $option->isValueOptional() ? 2 : 0; // [ + ]

                $nameLength += $valueLength;
            }
            $totalWidth = max($totalWidth, $nameLength);
        }

        return $totalWidth;
    }

    private function getTerminalWidth(array $options): int
    {
        if (isset($options['terminal_width'])) {
            return $options['terminal_width'];
        }

        if ($this->output instanceof StreamOutput && stream_isatty($this->output->getStream())) {
            return (new Terminal())->getWidth();
        }

        return \PHP_INT_MAX;
    }

    private function wrapText(string $text, int $width, int $indent): string
    {
        // 65535 is the PCRE quantifier limit OutputWrapper builds its pattern from; wider means no wrapping anyway
        if ($width > 0 && $width <= 65535) {
            // measures the visible width: formatter tags and multibyte characters do not count
            $text = (new OutputWrapper())->wrap($text, $width);
        }

        $text = str_replace("\r\n", "\n", $text);

        return str_replace("\n", "\n".str_repeat(' ', $indent), $text);
    }
}
