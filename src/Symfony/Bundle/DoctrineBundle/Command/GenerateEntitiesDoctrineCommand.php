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
            ->setDescription('Generate entity classes and method stubs from your mapping information')
            ->addArgument('name', InputArgument::REQUIRED, 'A bundle name, a namespace, or a class name')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The path where to generate entities when it cannot be guessed')
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

EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $bundle = $this->getApplication()->getKernel()->getBundle($input->getArgument('name'));

            $output->writeln(sprintf('Generating entities for bundle "<info>%s</info>"', $bundle->getName()));
            list($metadatas, $namespace, $path) = $this->getBundleInfo($bundle);
        } catch (\InvalidArgumentException $e) {
            $name = strtr($input->getArgument('name'), '/', '\\');

            if (false !== strpos($name, ':')) {
                $name = $this->getAliasedClassName($name);
            }

            if (class_exists($name)) {
                $output->writeln(sprintf('Generating entity "<info>%s</info>"', $name));
                list($metadatas, $namespace, $path) = $this->getClassInfo($name, $input->getOption('path'));
            } else {
                $output->writeln(sprintf('Generating entities for namespace "<info>%s</info>"', $name));
                list($metadatas, $namespace, $path) = $this->getNamespaceInfo($name, $input->getOption('path'));
            }
        }

        $generator = $this->getEntityGenerator();
        $repoGenerator = new EntityRepositoryGenerator();
        foreach ($metadatas as $metadata) {
            $output->writeln(sprintf('  > generating <comment>%s</comment>', $metadata->name));
            $generator->generate(array($metadata), $path);

            if ($metadata->customRepositoryClassName) {
                if (false === strpos($metadata->customRepositoryClassName, $namespace)) {
                    continue;
                }

                $repoGenerator->writeEntityRepositoryClass($metadata->customRepositoryClassName, $path);
            }
        }
    }

    private function getBundleInfo($bundle)
    {
        $namespace = $bundle->getNamespace();
        if (!$metadatas = $this->findMetadatasByNamespace($namespace)) {
            throw new \RuntimeException(sprintf('Bundle "%s" does not contain any mapped entities.', $bundle->getName()));
        }

        $path = $this->findBasePathForClass($bundle->getName(), $bundle->getNamespace(), $bundle->getPath());

        return array($metadatas, $bundle->getNamespace(), $path);
    }

    private function getClassInfo($class, $path)
    {
        if (!$metadatas = $this->findMetadatasByClass($class)) {
            throw new \RuntimeException(sprintf('Entity "%s" is not a mapped entity.', $class));
        }

        if (class_exists($class)) {
            $r = $metadatas[$class]->getReflectionClass();
            $path = $this->findBasePathForClass($class, $r->getNamespacename(), dirname($r->getFilename()));
        } elseif (!$path) {
            throw new \RuntimeException(sprintf('Unable to determine where to save the "%s" class (use the --path option).', $class));
        }

        return array($metadatas, $r->getNamespacename(), $path);
    }

    private function getNamespaceInfo($namespace, $path)
    {
        if (!$metadatas = $this->findMetadatasByNamespace($namespace)) {
            throw new \RuntimeException(sprintf('Namespace "%s" does not contain any mapped entities.', $namespace));
        }

        $first = reset($metadatas);
        $class = key($metadatas);
        if (class_exists($class)) {
            $r = $first->getReflectionClass();
            $path = $this->findBasePathForClass($namespace, $r->getNamespacename(), dirname($r->getFilename()));
        } elseif (!$path) {
            throw new \RuntimeException(sprintf('Unable to determine where to save the "%s" class (use the --path option).', $class));
        }

        return array($metadatas, $namespace, $path);
    }
}
