<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\Tools\EntityRepositoryGenerator;

/**
 * Command to generate repository classes for mapping information.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
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

  <info>./app/console doctrine:generate:repositories</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generator = new EntityRepositoryGenerator();
        foreach ($this->application->getKernel()->getBundles() as $bundle) {
            $destination = $bundle->getPath();
            if ($metadatas = $this->getBundleMetadatas($bundle)) {
                $output->writeln(sprintf('Generating entity repositories for "<info>%s</info>"', get_class($bundle)));
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