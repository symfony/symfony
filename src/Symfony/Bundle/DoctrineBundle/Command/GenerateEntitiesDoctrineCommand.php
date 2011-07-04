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
use Doctrine\ORM\Tools\EntityRepositoryGenerator;
use Symfony\Bundle\DoctrineBundle\Mapping\DisconnectedMetadataFactory;

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
            ->setAliases(array('generate:doctrine:entities'))
            ->setDescription('Generate entity classes and method stubs from your mapping information')
            ->addArgument('name', InputArgument::REQUIRED, 'A bundle name, a namespace, or a class name')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The path where to generate entities when it cannot be guessed')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not backup existing entities files.')
            ->setHelp(<<<EOT
The <info>doctrine:generate:entities</info> command generates entity classes
and method stubs from your mapping information:

You have to limit generation of entities:

* To a bundle:

  <info>./app/console doctrine:generate:entities MyCustomBundle</info>

* To a single entity:

  <info>./app/console doctrine:generate:entities MyCustomBundle:User</info>
  <info>./app/console doctrine:generate:entities MyCustomBundle/Entity/User</info>

* To a namespace

  <info>./app/console doctrine:generate:entities MyCustomBundle/Entity</info>

If the entities are not stored in a bundle, and if the classes do not exist,
the command has no way to guess where they should be generated. In this case,
you must provide the <comment>--path</comment> option:

  <info>./app/console doctrine:generate:entities Blog/Entity --path=src/</info>

You should provide the <comment>--no-backup</comment> option if you don't mind to back up files
before to generate entities:

  <info>./app/console doctrine:generate:entities Blog/Entity --no-backup</info>

EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = new DisconnectedMetadataFactory($this->getContainer()->get('doctrine'));

        try {
            $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('name'));

            $output->writeln(sprintf('Generating entities for bundle "<info>%s</info>"', $bundle->getName()));
            $metadata = $manager->getBundleMetadata($bundle);
        } catch (\InvalidArgumentException $e) {
            $name = strtr($input->getArgument('name'), '/', '\\');

            if (false !== $pos = strpos($name, ':')) {
                $name = $this->getContainer()->get('doctrine')->getEntityNamespace(substr($name, 0, $pos)).'\\'.substr($name, $pos + 1);
            }

            if (class_exists($name)) {
                $output->writeln(sprintf('Generating entity "<info>%s</info>"', $name));
                $metadata = $manager->getClassMetadata($name, $input->getOption('path'));
            } else {
                $output->writeln(sprintf('Generating entities for namespace "<info>%s</info>"', $name));
                $metadata = $manager->getNamespaceMetadata($name, $input->getOption('path'));
            }
        }

        $generator = $this->getEntityGenerator();
        $generator->setBackupExisting(!$input->getOption('no-backup'));
        $repoGenerator = new EntityRepositoryGenerator();
        foreach ($metadata->getMetadata() as $m) {
            $output->writeln(sprintf('  > generating <comment>%s</comment>', $m->name));
            $generator->setAnnotationPrefix('ORM\\');
            $generator->generate(array($m), $metadata->getPath());

            if ($m->customRepositoryClassName && false !== strpos($m->customRepositoryClassName, $metadata->getNamespace())) {
                $repoGenerator->writeEntityRepositoryClass($m->customRepositoryClassName, $metadata->getPath());
            }
        }
    }
}
