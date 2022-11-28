<?php

declare(strict_types=1);

namespace Symfony\Component\ImportMaps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ImportMaps\Env;
use Symfony\Component\ImportMaps\Provider;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:update', description: 'Updates all JavaScript packages to their latest versions')]
final class UpdateCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->importMapManager->update(
            Env::from($input->getOption('js-env')),
            Provider::from($input->getOption('provider')),
        );

        return Command::SUCCESS;
    }
}
