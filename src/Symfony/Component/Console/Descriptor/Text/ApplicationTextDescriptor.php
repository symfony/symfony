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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Descriptor\ApplicationDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ApplicationTextDescriptor extends AbstractTextDescriptor
{
    /**
     * @var string|null
     */
    private $namespace;

    /**
     * @param string|null $namespace
     */
    public function __construct($namespace = null)
    {
        $this->namespace = $namespace;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        if (isset($options['namespace'])) {
            $this->namespace = $options['namespace'];
        }

        return parent::configure($options);
    }

    /**
     * {@inheritdoc}
     */
    public function getRawText($object)
    {
        /** @var Application $object */
        $description = new ApplicationDescription($object, $this->namespace);
        $width = $this->getColumnWidth($description->getCommands());
        $messages = array();

        foreach ($description->getCommands() as $command) {
            $messages[] = sprintf("%-${width}s %s", $command->getName(), $command->getDescription());
        }

        return implode(PHP_EOL, $messages);
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedText($object)
    {
        /** @var Application $object */
        $description = new ApplicationDescription($object, $this->namespace);
        $width = $this->getColumnWidth($description->getCommands());
        $messages = array($object->getHelp(), '');

        if ($this->namespace) {
            $messages[] = sprintf("<comment>Available commands for the \"%s\" namespace:</comment>", $this->namespace);
        } else {
            $messages[] = '<comment>Available commands:</comment>';
        }

        // add commands by namespace
        foreach ($description->getNamespaces() as $namespace) {
            if (!$this->namespace && ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
                $messages[] = '<comment>'.$namespace['id'].'</comment>';
            }

            foreach ($namespace['commands'] as $name) {
                $messages[] = sprintf("  <info>%-${width}s</info> %s", $name, $description->getCommand($name)->getDescription());
            }
        }

        return implode(PHP_EOL, $messages);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Application;
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
