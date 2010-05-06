<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Foundation\Bundle\Bundle;
use Doctrine\ORM\Tools\EntityGenerator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Generate entity classes from mapping information
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateEntitiesDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:entities')
            ->setDescription('Generate entity classes and method stubs from your mapping information.')
            ->setHelp(<<<EOT
The <info>doctrine:generate:entities</info> command generates entity classes and method stubs from your mapping information:

  <info>./symfony doctrine:generate:entities</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entityGenerator = $this->getEntityGenerator();
        $bundleDirs = $this->container->getKernelService()->getBundleDirs();
        foreach ($this->container->getKernelService()->getBundles() as $bundle)
        {
            $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
            $namespace = str_replace('/', '\\', dirname($tmp));
            $class = basename($tmp);

            if (isset($bundleDirs[$namespace]))
            {
                $destination = realpath($bundleDirs[$namespace].'/..');
                if ($metadatas = $this->getBundleMetadatas($bundle))
                {
                    $output->writeln(sprintf('Generating entities for "<info>%s</info>"', $class));
                    foreach ($metadatas as $metadata)
                    {
                        $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->name));
                        $entityGenerator->generate(array($metadata), $destination);
                    }
                }
            }
        }
    }
}