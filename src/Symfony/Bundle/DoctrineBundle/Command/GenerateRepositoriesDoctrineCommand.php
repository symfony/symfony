<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;

/**
 * Command to generate repository classes for mapping information.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateRepositoriesDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:repositories')
            ->setDescription('Generate repository classes from your mapping information.')
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to initialize the repositories in.')
            ->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'The entity class to generate the repository for (shortname without namespace).')
            ->setHelp(<<<EOT
The <info>doctrine:generate:repositories</info> command generates the configured entity repository classes from your mapping information:

  <info>./app/console doctrine:generate:repositories</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName = $input->getArgument('bundle');
        $filterEntity = $input->getOption('entity');

        $foundBundle = $this->getApplication()->getKernel()->getBundle($bundleName);

        if ($metadatas = $this->getBundleMetadatas($foundBundle)) {
            $output->writeln(sprintf('Generating entity repositories for "<info>%s</info>"', $foundBundle->getName()));
            $generator = new EntityRepositoryGenerator();

            foreach ($metadatas as $metadata) {
                if ($filterEntity && $filterEntity !== $metadata->reflClass->getShortname()) {
                    continue;
                }

                if ($metadata->customRepositoryClassName) {
                    if (strpos($metadata->customRepositoryClassName, $foundBundle->getNamespace()) === false) {
                        throw new \RuntimeException(
                            "Repository " . $metadata->customRepositoryClassName . " and bundle don't have a common namespace, ".
                            "generation failed because the target directory cannot be detected.");
                    }

                    $output->writeln(sprintf('  > <info>OK</info> generating <comment>%s</comment>', $metadata->customRepositoryClassName));
                    $generator->writeEntityRepositoryClass($metadata->customRepositoryClassName, $this->findBasePathForBundle($foundBundle));
                } else {
                    $output->writeln(sprintf('  > <error>SKIP</error> no custom repository for <comment>%s</comment>', $metadata->name));
                }
            }
        } else {
            throw new \RuntimeException("Bundle " . $bundleName . " does not contain any mapped entities.");
        }
    }
}