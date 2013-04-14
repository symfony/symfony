<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Markdown;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\CommandDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class CommandMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function describe($object)
    {
        /** @var Command $object */
        $description = new CommandDescription($object);
        $definitionDescriptor = new InputDefinitionMarkdownDescriptor();

        $markdown = $description->getName()."\n"
            .str_repeat('-', strlen($description->getName()))."\n\n"
            .'* Description: '.($description->getDescription() ?: '<none>')."\n"
            .'* Usage: `'.$description->getSynopsis().'`'."\n"
            .'* Aliases: '.(count($description->getAliases()) ? '`'.implode('`, `', $description->getAliases()).'`' : '<none>');

        if ($description->getHelp()) {
            $markdown .= "\n\n".$description->getHelp();
        }

        $definitionMarkdown = $definitionDescriptor->describe($description->getDefinition());
        if ($definitionMarkdown) {
            $markdown .= "\n\n".$definitionMarkdown;
        }

        return $markdown;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Command;
    }
}
