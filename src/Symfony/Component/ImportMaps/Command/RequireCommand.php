<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ImportMaps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ImportMaps\Env;
use Symfony\Component\ImportMaps\Provider;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:require', description: 'Requires JavaScript packages')]
final class RequireCommand extends AbstractCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The packages to add');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->importMapManager->require(
            $input->getArgument('packages'),
            Env::from($input->getOption('js-env')),
        );

        return Command::SUCCESS;
    }
}
