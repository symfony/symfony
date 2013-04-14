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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Descriptor\ApplicationDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ApplicationJsonDescriptor extends AbstractJsonDescriptor
{
    /**
     * @var string|null
     */
    private $namespace;

    /**
     * @param string|null $namespace
     * @param int         $encodingOptions
     */
    public function __construct($namespace = null, $encodingOptions = 0)
    {
        $this->namespace = $namespace;
        parent::__construct($encodingOptions);
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
    public function getData($object)
    {
        /** @var Application $object */
        $description = new ApplicationDescription($object, $this->namespace);
        $commands = array();

        foreach ($description->getCommands() as $command) {
            $commands[] = $this->getDescriptor($command)->getData($command);
        }

        return null === $this->namespace
            ? array('commands' => $commands, 'namespaces' => array_values($description->getNamespaces()))
            : array('commands' => $commands, 'namespace' => $this->namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Application;
    }
}
