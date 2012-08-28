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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Symfony\Bundle\FrameworkBundle\Requirement\SymfonyRequirements;
use Symfony\Bundle\FrameworkBundle\Requirement\Requirement;

/**
 * Checks if the server has all the requirements to run a Symfony2 application
 *
 * @author Tiago Ribeiro <tiago.ribeiro@seegno.com>
 * @author Rui Marinho <rui.marinho@seegno.com>
 */
class ServerStatusCommand extends ContainerAwareCommand
{
    protected $output;

    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('server:status')
            ->setDescription('Checks if the server has all the requirements to run a Symfony2 application')
            ->setHelp(<<<EOF
The <info>%command.name%</info> checks several requirements
from your server php.ini configuration
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $symfonyRequirements = new SymfonyRequirements();

        $iniPath = $symfonyRequirements->getPhpIniConfigPath();

        $output->writeln($this->getHelper('formatter')->formatSection("Symfony", "Checking requirements\n"));

        if ($iniPath) {
            $output->writeln(sprintf("<fg=yellow>Configuration file used by PHP: %s</>", $iniPath));
        } else {
            $output->writeln(sprintf("<fg=red>WARNING: No configuration file (php.ini) used by PHP!</>"));
        }

        $this->renderTitle('Mandatory requirements');

        foreach ($symfonyRequirements->getRequirements() as $req) {
            $this->renderRequirement($req);
        }

        $this->renderTitle('Optional recommendations');

        foreach ($symfonyRequirements->getRecommendations() as $req) {
            $this->renderRequirement($req);
        }
    }

    protected function renderTitle($title)
    {
        $this->output->writeln('');
        $this->output->writeln($this->getHelper('formatter')->formatSection("Symfony", sprintf("%s", $title)));
        $this->output->writeln('');
    }

    /**
     * Prints a Requirement instance
     */
    protected function renderRequirement(Requirement $requirement)
    {
        $result = $requirement->isFulfilled() ? 'OK' : ($requirement->isOptional() ? 'WARNING' : 'ERROR');
        $this->output->write(' ' . str_pad($result, 9) . $requirement->getTestMessage() . "\n");

        if (!$requirement->isFulfilled()) {
            $this->output->write(sprintf("          %s\n\n", $requirement->getHelpText()));
        }
    }
}
