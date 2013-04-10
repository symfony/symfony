<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Descriptor\Json;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\CommandDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class CommandJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * {@inheritdoc}
     */
    public function getData($object)
    {
        $definitionDescriptor = new InputDefinitionJsonDescriptor();
        /** @var Command $object */
        $description = new CommandDescription($object);

        return array(
            'name'        => $description->getName(),
            'usage'       => $description->getSynopsis(),
            'description' => $description->getDescription(),
            'help'        => $description->getHelp(),
            'aliases'     => $description->getAliases(),
            'definition'  => $definitionDescriptor->getData($description->getDefinition()),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Command;
    }
}
