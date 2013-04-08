<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Text;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class CommandTextDescriptor extends AbstractTextDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getRawText($object)
    {
        return strip_tags($this->getFormattedText($object));
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedText($object)
    {
        /** @var Command $object */
        $messages = array('<comment>Usage:</comment>', ' '.$object->getSynopsis(), '');

        if ($object->getAliases()) {
            $messages[] = '<comment>Aliases:</comment> <info>'.implode(', ', $object->getAliases()).'</info>';
        }

        $descriptor = new InputDefinitionTextDescriptor();
        $messages[] = $descriptor->getFormattedText($object->getDefinition());

        if ($help = $object->getProcessedHelp()) {
            $messages[] = '<comment>Help:</comment>';
            $messages[] = ' '.str_replace("\n", "\n ", $help)."\n";
        }

        return implode("\n", $messages);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Command;
    }
}
