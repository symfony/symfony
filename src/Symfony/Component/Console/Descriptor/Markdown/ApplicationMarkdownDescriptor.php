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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Descriptor\ApplicationDescription;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class ApplicationMarkdownDescriptor extends AbstractMarkdownDescriptor
{
    /**
     * @var string|null
     */
    private $namespace;

    /**
     * @param string|null $namespace
     * @param int         $maxWidth
     */
    public function __construct($namespace = null, $maxWidth = 120)
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
    public function describe($object)
    {
        /** @var Application $object */
        $description = new ApplicationDescription($object, $this->namespace);
        $blocks = array($object->getName()."\n".str_repeat('=', strlen($object->getName())));

        foreach ($description->getNamespaces() as $namespace) {
            if (ApplicationDescription::GLOBAL_NAMESPACE !== $namespace['id']) {
                $blocks[] = '**'.$namespace['id'].':**';
            }

            $blocks[] = implode("\n", array_map(function ($commandName) {
                return '* '.$commandName;
            } , $namespace['commands']));
        }

        foreach ($description->getCommands() as $command) {
            $blocks[] = $this->getDescriptor($command)->describe($command);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($object)
    {
        return $object instanceof Application;
    }
}
