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

/**
 * Generate entity classes from mapping information
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateEntitiesDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:entities')
            ->setDescription('Generate entity classes and method stubs from your mapping information.')
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to initialize the entity or entities in.')
            ->addOption('entity', null, InputOption::VALUE_OPTIONAL, 'The entity class to initialize (shortname without namespace).')
            ->setHelp(<<<EOT
The <info>doctrine:generate:entities</info> command generates entity classes and method stubs from your mapping information:

You have to limit generation of entities to an individual bundle:

  <info>./app/console doctrine:generate:entities MyCustomBundle</info>

Alternatively, you can limit generation to a single entity within a bundle:

  <info>./app/console doctrine:generate:entities "MyCustomBundle" --entity="User"</info>

You have to specify the shortname (without namespace) of the entity you want to filter for.
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleName = $input->getArgument('bundle');
        $filterEntity = $input->getOption('entity');

        $foundBundle = $this->getApplication()->getKernel()->getBundle($bundleName);

        if ($metadatas = $this->getBundleMetadatas($foundBundle)) {
            $output->writeln(sprintf('Generating entities for "<info>%s</info>"', $foundBundle->getName()));
            $entityGenerator = $this->getEntityGenerator();

            foreach ($metadatas as $metadata) {
                if ($filterEntity && $metadata->getReflClass()->getShortName() !== $filterEntity) {
                    continue;
                }

                if (strpos($metadata->name, $foundBundle->getNamespace()) === false) {
                    throw new \RuntimeException(
                        "Entity " . $metadata->name . " and bundle don't have a common namespace, ".
                        "generation failed because the target directory cannot be detected.");
                }

                $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->name));
                $entityGenerator->generate(array($metadata), $this->findBasePathForBundle($foundBundle));
            }
        } else {
            throw new \RuntimeException("Bundle " . $bundleName . " does not contain any mapped entities.");
        }
    }
}