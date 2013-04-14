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
        $object->getSynopsis();
        $object->mergeApplicationDefinition(false);

        $markdown = $object->getName()."\n"
            .str_repeat('-', strlen($object->getName()))."\n\n"
            .'* Description: '.($object->getDescription() ?: '<none>')."\n"
            .'* Usage: `'.$object->getSynopsis().'`'."\n"
            .'* Aliases: '.(count($object->getAliases()) ? '`'.implode('`, `', $object->getAliases()).'`' : '<none>');

        if ($help = $object->getProcessedHelp()) {
            $markdown .= "\n\n".$help;
        }

        $definition = $object->getNativeDefinition();
        $definitionMarkdown = $this->getDescriptor($definition)->describe($definition);
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
