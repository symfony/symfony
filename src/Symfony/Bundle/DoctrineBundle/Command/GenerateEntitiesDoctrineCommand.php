<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\HttpKernel\Bundle\Bundle;
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
            ->addOption('bundle', null, InputOption::VALUE_OPTIONAL, 'The bundle to initialize the entity or entities in.')
            ->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'The entity class to initialize (requires bundle parameter).')
            ->setHelp(<<<EOT
The <info>doctrine:generate:entities</info> command generates entity classes and method stubs from your mapping information:

  <info>./symfony doctrine:generate:entities</info>

The above would generate entity classes for all bundles.

You can also optionally limit generation to entities within an individual bundle:

  <info>./symfony doctrine:generate:entities --bundle="Bundle/MyCustomBundle"</info>

Alternatively, you can limit generation to a single entity within a bundle:

  <info>./symfony doctrine:generate:entities --bundle="Bundle/MyCustomBundle" --entity="User"</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filterBundle = $input->getOption('bundle') ? str_replace('/', '\\', $input->getOption('bundle')) : false;
        $filterEntity = $filterBundle ? $filterBundle . '\\Entity\\' . str_replace('/', '\\', $input->getOption('entity')) : false;

        if (!isset($filterBundle) && isset($filterEntity)) {
            throw new \InvalidArgumentException(sprintf('Unable to specify an entity without also specifying a bundle.'));
        }

        $entityGenerator = $this->getEntityGenerator();
        foreach ($this->container->get('kernel')->getBundles() as $bundle) {

            // retrieve the full bundle classname
            $class = $bundle->getReflection()->getName();

            if ($filterBundle && $filterBundle != $class) {
                continue;
            }

            // transform classname to a path and substract it to get the destination
            $path = dirname(str_replace('\\', '/', $class));
            $destination = str_replace('/'.$path, "", $bundle->getPath());

            if ($metadatas = $this->getBundleMetadatas($bundle)) {
                $output->writeln(sprintf('Generating entities for "<info>%s</info>"', $class));

                foreach ($metadatas as $metadata) {
                    if ($filterEntity && strpos($metadata->name, $filterEntity) !== 0) {
                        continue;
                    }
                    $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->name));
                    $entityGenerator->generate(array($metadata), $destination);
                }
            }
        }
    }
}