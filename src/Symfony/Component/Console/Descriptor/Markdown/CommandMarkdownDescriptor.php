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
    public function getDocument($object)
    {
        /** @var Command $object */
        $description = new CommandDescription($object);
        $definitionDescriptor = new InputDefinitionMarkdownDescriptor();

        return new Document\Document(array(
            new Document\Title($description->getName(), 2),
            new Document\UnorderedList(array(
                'Description: '.($description->getDescription() ?: '<none>'),
                'Usage: `'.$description->getSynopsis().'`',
                'Aliases: '.(count($description->getAliases()) ? '`'.implode('`, `', $description->getAliases()).'`' : '<none>'),
            )),
            new Document\Paragraph($description->getHelp()),
            $definitionDescriptor->getDocument($description->getDefinition()),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Command;
    }
}
