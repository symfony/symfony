<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Command to generate repository classes for mapping information.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateRepositoriesDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:repositories')
            ->setDescription('Generate repository classes from your mapping information.')
            ->setHelp(<<<EOT
The <info>doctrine:generate:repositories</info> command generates the configured entity repository classes from your mapping information:

  <info>./symfony doctrine:generate:repositories</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generator = new EntityRepositoryGenerator();
        $kernel = $this->application->getKernel();
        $bundleDirs = $kernel->getBundleDirs();
        foreach ($kernel->getBundles() as $bundle) {
            $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
            $namespace = str_replace('/', '\\', dirname($tmp));
            $class = basename($tmp);

            if (isset($bundleDirs[$namespace])) {
                $destination = realpath($bundleDirs[$namespace].'/..');
                if ($metadatas = $this->getBundleMetadatas($bundle)) {
                    $output->writeln(sprintf('Generating entity repositories for "<info>%s</info>"', $class));
                    foreach ($metadatas as $metadata) {
                        if ($metadata->customRepositoryClassName) {
                            $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->customRepositoryClassName));
                            $generator->writeEntityRepositoryClass($metadata->customRepositoryClassName, $destination);
                        }
                    }
                }
            }
        }
    }
}