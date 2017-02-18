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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\Command\XliffLintCommand as BaseLintCommand;

/**
 * Validates XLIFF files syntax and outputs encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class XliffLintCommand extends Command
{
    private $command;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('lint:xliff');

        if (!$this->isEnabled()) {
            return;
        }

        $directoryIteratorProvider = function ($directory, $default) {
            if (!is_dir($directory)) {
                $directory = $this->getApplication()->getKernel()->locateResource($directory);
            }

            return $default($directory);
        };

        $isReadableProvider = function ($fileOrDirectory, $default) {
            return 0 === strpos($fileOrDirectory, '@') || $default($fileOrDirectory);
        };

        $this->command = new BaseLintCommand(null, $directoryIteratorProvider, $isReadableProvider);

        $this
            ->setDescription($this->command->getDescription())
            ->setDefinition($this->command->getDefinition())
            ->setHelp($this->command->getHelp().<<<'EOF'

Or find all files in a bundle:

  <info>php %command.full_name% @AcmeDemoBundle</info>

EOF
            );
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return class_exists(BaseLintCommand::class);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->command->execute($input, $output);
    }
}
