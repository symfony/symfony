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

use Exception;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\String\UnicodeString;

class ReStructuredTextDescriptor extends Descriptor
{
    // <h1>
    private string $partChar = '#';
    // <h2>
    private string $chapterChar = '*';
    // <h3>
    private string $sectionChar = '=';
    // <h4>
    private string $subsectionChar = '-';
    // <h5>
    private string $subsubsectionChar = '^';
    // <h6>
    private string $paragraphsChar = '"';

    private array $visibleNamespaces = [];

    public function describe(OutputInterface $output, object $object, array $options = []): void
    {
        $decorated = $output->isDecorated();
        $output->setDecorated(false);

        parent::describe($output, $object, $options);

        $output->setDecorated($decorated);
    }

    /**
     * Override parent method to set $decorated = true.
     */
    protected function write(string $content, bool $decorated = true): void
    {
        parent::write($content, $decorated);
    }

    protected function describeInputArgument(InputArgument $argument, array $options = []): void
    {
        $this->write(
            $argument->getName() ?: '<none>'."\n".str_repeat($this->paragraphsChar, Helper::width($argument->getName()))."\n\n"
                .($argument->getDescription() ? preg_replace('/\s*[\r\n]\s*/', "\n", $argument->getDescription())."\n\n" : '')
                .'- **Is required**: '.($argument->isRequired() ? 'yes' : 'no')."\n"
                .'- **Is array**: '.($argument->isArray() ? 'yes' : 'no')."\n"
                .'- **Default**: ``'.str_replace("\n", '', var_export($argument->getDefault(), true)).'``'
        );
    }

    protected function describeInputOption(InputOption $option, array $options = []): void
    {
        $name = '\-\-'.$option->getName();
        if ($option->isNegatable()) {
            $name .= '|\-\-no-'.$option->getName();
        }
        if ($option->getShortcut()) {
            $name .= '|-'.str_replace('|', '|-', $option->getShortcut());
        }

        $option_description = $option->getDescription() ? preg_replace('/\s*[\r\n]\s*/', "\n\n", $option->getDescription())."\n\n" : '';
        $option_description = (new UnicodeString($option_description))->ascii();
        $this->write(
            $name."\n".str_repeat($this->paragraphsChar, Helper::width($name))."\n\n"
            .$option_description
            .'- **Accept value**: '.($option->acceptValue() ? 'yes' : 'no')."\n"
            .'- **Is value required**: '.($option->isValueRequired() ? 'yes' : 'no')."\n"
            .'- **Is multiple**: '.($option->isArray() ? 'yes' : 'no')."\n"
            .'- **Is negatable**: '.($option->isNegatable() ? 'yes' : 'no')."\n"
            .'- **Default**: ``'.str_replace("\n", '', var_export($option->getDefault(), true)).'``'."\n"
        );
    }

    protected function describeInputDefinition(InputDefinition $definition, array $options = []): void
    {
        if ($showArguments = (\count($definition->getArguments()) > 0)) {
            $this->write("Arguments\n".str_repeat($this->subsubsectionChar, 9))."\n\n";
            foreach ($definition->getArguments() as $argument) {
                $this->write("\n\n");
                $this->describeInputArgument($argument);
            }
        }

        $non_default_options = $this->getNonDefaultOptions($definition);
        if (\count($non_default_options) > 0) {
            if ($showArguments) {
                $this->write("\n\n");
            }

            $this->write("Options\n".str_repeat($this->subsubsectionChar, 7)."\n\n");
            foreach ($non_default_options as $option) {
                $this->describeInputOption($option);
                $this->write("\n");
            }
        }
    }

    protected function describeCommand(Command $command, array $options = []): void
    {
        if ($options['short'] ?? false) {
            $this->write(
                '``'.$command->getName()."``\n"
                .str_repeat($this->subsectionChar, Helper::width($command->getName()))."\n\n"
                .($command->getDescription() ? $command->getDescription()."\n\n" : '')
                ."Usage\n".str_repeat($this->paragraphsChar, 5)."\n\n"
                .array_reduce($command->getAliases(), static function ($carry, $usage) {
                    return $carry.'- ``'.$usage.'``'."\n";
                })
            );

            return;
        }

        $command->mergeApplicationDefinition(false);

        foreach ($command->getAliases() as $alias) {
            $this->write('.. _'.$alias.":\n\n");
        }
        $this->write(
            $command->getName()."\n"
            .str_repeat($this->subsectionChar, Helper::width($command->getName()))."\n\n"
            .($command->getDescription() ? $command->getDescription()."\n\n" : '')
            ."Usage\n".str_repeat($this->subsubsectionChar, 5)."\n\n"
            .array_reduce(array_merge([$command->getSynopsis()], $command->getAliases(), $command->getUsages()), static function ($carry, $usage) {
                return $carry.'- ``'.$usage.'``'."\n";
            })
        );

        if ($help = $command->getProcessedHelp()) {
            $this->write("\n");
            $this->write($help);
        }

        $definition = $command->getDefinition();
        if ($definition->getOptions() || $definition->getArguments()) {
            $this->write("\n\n");
            $this->describeInputDefinition($definition);
        }
    }

    protected function describeApplication(Application $application, array $options = []): void
    {
        $describedNamespace = $options['namespace'] ?? null;
        $description = new ApplicationDescription($application, $describedNamespace);
        $title = $this->getApplicationTitle($application);

        $this->write($title."\n".str_repeat($this->partChar, Helper::width($title)));
        $this->createTableOfContents($description, $application);
        $this->describeCommands($application, $options);
    }

    private function getApplicationTitle(Application $application): string
    {
        if ('UNKNOWN' !== $application->getName()) {
            if ('UNKNOWN' !== $application->getVersion()) {
                return sprintf('%s %s', $application->getName(), $application->getVersion());
            }

            return $application->getName();
        }

        return 'Console Tool';
    }

    private function describeCommands($application, array $options): void
    {
        $title = 'Commands';
        $this->write("\n\n$title\n".str_repeat($this->chapterChar, Helper::width($title))."\n\n");
        foreach ($this->visibleNamespaces as $namespace) {
            if ('_global' === $namespace) {
                $commands = $application->all('');
                $this->write('Global'."\n".str_repeat($this->sectionChar, Helper::width('Global'))."\n\n");
            } else {
                $commands = $application->all($namespace);
                $this->write($namespace."\n".str_repeat($this->sectionChar, Helper::width($namespace))."\n\n");
            }
            $commands = $this->removeAliasesAndHiddenCommands($commands);

            foreach ($commands as $command) {
                $this->describeCommand($command, $options);
                $this->write("\n\n");
            }
        }
    }

    /**
     * @param \Symfony\Component\Console\Descriptor\ApplicationDescription $description
     */
    private function createTableOfContents(ApplicationDescription $description, Application $application): void
    {
        $this->setVisibleNamespaces($description);
        $chapter_title = 'Table of Contents';
        $this->write("\n\n$chapter_title\n".str_repeat($this->chapterChar, Helper::width($chapter_title))."\n\n");
        foreach ($this->visibleNamespaces as $namespace) {
            if ('_global' === $namespace) {
                $commands = $application->all('');
            } else {
                $commands = $application->all($namespace);
                $this->write("\n\n");
                $this->write($namespace."\n".str_repeat($this->sectionChar, Helper::width($namespace))."\n\n");
            }
            $commands = $this->removeAliasesAndHiddenCommands($commands);

            $this->write("\n\n");
            $this->write(implode("\n", array_map(static function ($commandName) {
                return sprintf('- `%s`_', $commandName);
            }, array_keys($commands))));
        }
    }

    private function getNonDefaultOptions(InputDefinition $definition): array
    {
        $global_options = [
            'help',
            'quiet',
            'verbose',
            'version',
            'ansi',
            'no-interaction',
        ];
        $non_default_options = [];
        foreach ($definition->getOptions() as $option) {
            // Skip global options.
            // @todo Show these once at the beginning.
            if (!\in_array($option->getName(), $global_options)) {
                $non_default_options[] = $option;
            }
        }

        return $non_default_options;
    }

    /**
     * @param \Symfony\Component\Console\Descriptor\ApplicationDescription $description
     */
    private function setVisibleNamespaces(ApplicationDescription $description): void
    {
        $commands = $description->getCommands();
        foreach ($description->getNamespaces() as $namespace) {
            try {
                // Remove aliases.
                $namespace_commands = $namespace['commands'];
                foreach ($namespace_commands as $key => $command_name) {
                    if (!\array_key_exists($command_name, $commands)) {
                        // If the array key does not exist, then this is an alias.
                        unset($namespace_commands[$key]);
                    } elseif ($commands[$command_name]->isHidden()) {
                        unset($namespace_commands[$key]);
                    }
                }
                if (!\count($namespace_commands)) {
                    // If the namespace contained only aliases or hidden commands, skip the namespace.
                    continue;
                }
            } catch (Exception) {
            }
            $this->visibleNamespaces[] = $namespace['id'];
        }
    }

    private function removeAliasesAndHiddenCommands(array $commands): array
    {
        // Remove aliases.
        foreach ($commands as $key => $command) {
            if ($command->isHidden() || \in_array($key, $command->getAliases(), true)) {
                unset($commands[$key]);
            }
        }
        unset($commands['completion']);

        return $commands;
    }
}
