<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Yaml\Command\LintCommand as BaseLintCommand;

/**
 * Validates YAML files syntax and outputs encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class YamlLintCommand extends BaseLintCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setHelp(
            $this->getHelp().<<<EOF

Or find all files in a bundle:

  <info>php %command.full_name% @AcmeDemoBundle</info>

EOF
        );
    }

    protected function getDirectoryIterator($directory)
    {
        if (!is_dir($directory)) {
            $directory = $this->getApplication()->getKernel()->locateResource($directory);
        }

        return parent::getDirectoryIterator($directory);
    }

    protected function isReadable($fileOrDirectory)
    {
        return 0 === strpos($fileOrDirectory, '@') || parent::isReadable($fileOrDirectory);
    }
}
