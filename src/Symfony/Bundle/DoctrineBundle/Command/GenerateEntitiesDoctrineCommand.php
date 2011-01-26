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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Doctrine\ORM\Tools\EntityGenerator;

/**
 * Generate entity classes from mapping information
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateEntitiesDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:entities')
            ->setDescription('Generate entity classes and method stubs from your mapping information.')
            ->addOption('bundle', null, InputOption::VALUE_REQUIRED, 'The bundle to initialize the entity or entities in.')
            ->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'The entity class to initialize (shortname without namespace).')
            ->setHelp(<<<EOT
The <info>doctrine:generate:entities</info> command generates entity classes and method stubs from your mapping information:

You have to limit generation of entities to an individual bundle:

  <info>./app/console doctrine:generate:entities --bundle="MyCustomBundle"</info>

Alternatively, you can limit generation to a single entity within a bundle:

  <info>./app/console doctrine:generate:entities --bundle="MyCustomBundle" --entity="User"</info>

You have to specifiy the shortname (without namespace) of the entity you want to filter for.
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filterEntity = $input->getOption('entity');

        $entityGenerator = $this->getEntityGenerator();
        foreach ($this->application->getKernel()->getBundles() as $bundle) {
            /* @var $bundle Bundle */
            if ($input->getOption('bundle') != $bundle->getName()) {
                continue;
            }

            // transform classname to a path and substract it to get the destination
            $path = dirname(str_replace('\\', '/', $bundle->getNamespace()));
            $destination = str_replace('/'.$path, "", $bundle->getPath());

            if ($metadatas = $this->getBundleMetadatas($bundle)) {
                $output->writeln(sprintf('Generating entities for "<info>%s</info>"', $class));

                foreach ($metadatas as $metadata) {
                    if ($filterEntity && $metadata->reflClass->getShortName() == $filterEntity) {
                        continue;
                    }
                    $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->name));
                    $entityGenerator->generate(array($metadata), $destination);
                }
            }
        }
    }
}