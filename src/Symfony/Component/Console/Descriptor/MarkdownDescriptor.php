<?php

namespace Symfony\Component\Console\Descriptor;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

/**
 * Markdown descriptor.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class MarkdownDescriptor implements DescriptorInterface
{
    /**
     * {@inheritdoc}
     */
    public function describeInputArgument(InputArgument $argument, array $options = array())
    {
        return '**'.$argument->getName().':**'."\n\n"
            .'* Name: '.($argument->getName() ?: '<none>')."\n"
            .'* Is required: '.($argument->isRequired() ? 'yes' : 'no')."\n"
            .'* Is array: '.($argument->isArray() ? 'yes' : 'no')."\n"
            .'* Description: '.($argument->getDescription() ?: '<none>')."\n"
            .'* Default: `'.str_replace("\n", '', var_export($argument->getDefault(), true)).'`';
    }

    /**
     * {@inheritdoc}
     */
    public function describeInputOption(InputOption $option, array $options = array())
    {
        return '**'.$option->getName().':**'."\n\n"
            .'* Name: `--'.$option->getName().'`'."\n"
            .'* Shortcut: '.($option->getShortcut() ? '`-'.$option->getShortcut().'`' : '<none>')."\n"
            .'* Accept value: '.($option->acceptValue() ? 'yes' : 'no')."\n"
            .'* Is value required: '.($option->isValueRequired() ? 'yes' : 'no')."\n"
            .'* Is multiple: '.($option->isArray() ? 'yes' : 'no')."\n"
            .'* Description: '.($option->getDescription() ?: '<none>')."\n"
            .'* Default: `'.str_replace("\n", '', var_export($option->getDefault(), true)).'`';
    }

    /**
     * {@inheritdoc}
     */
    public function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
        $blocks = array();

        if (count($definition->getArguments()) > 0) {
            $blocks[] = '### Arguments:';
            foreach ($definition->getArguments() as $argument) {
                $blocks[] = $this->describeInputArgument($argument);
            }
        }

        if (count($definition->getOptions()) > 0) {
            $blocks[] = '### Options:';
            foreach ($definition->getOptions() as $option) {
                $blocks[] = $this->describeInputOption($option);
            }
        }

        return implode("\n\n", $blocks);
    }

    /**
     * {@inheritdoc}
     */
    public function describeCommand(Command $command, array $options = array())
    {
        $command->getSynopsis();
        $command->mergeApplicationDefinition(false);

        $markdown = $command->getName()."\n"
            .str_repeat('-', strlen($command->getName()))."\n\n"
            .'* Description: '.($command->getDescription() ?: '<none>')."\n"
            .'* Usage: `'.$command->getSynopsis().'`'."\n"
            .'* Aliases: '.(count($command->getAliases()) ? '`'.implode('`, `', $command->getAliases()).'`' : '<none>');

        if ($help = $command->getProcessedHelp()) {
            $markdown .= "\n\n".$help;
        }

        if ($definitionMarkdown = $this->describeInputDefinition($command->getNativeDefinition())) {
            $markdown .= "\n\n".$definitionMarkdown;
        }

        return $markdown;
    }

    /**
     * {@inheritdoc}
     */
    public function describeApplication(Application $application, array $options = array())
    {
        $describedNamespace = isset($options['namespace']) ? $options['namespace'] : null;
        $description = new ApplicationDescription($application, $describedNamespace);
        $blocks = array($application->getName()."\n".str_repeat('=', strlen($application->getName())));

        foreach ($description->getNamespaces() as $namespace) {
            if (ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
                $blocks[] = '**'.$namespace['id'].':**';
            }

            $blocks[] = implode("\n", array_map(function ($commandName) {
                return '* '.$commandName;
            } , $namespace['commands']));
        }

        foreach ($description->getCommands() as $command) {
            $blocks[] = $this->describeCommand($command);
        }

        return implode("\n\n", $blocks);
    }
}
