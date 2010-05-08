<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Components\Finder\Finder;
use Symfony\Framework\WebBundle\Util\Filesystem;
use Doctrine\Common\Cli\Configuration;
use Doctrine\Common\Cli\CliController as DoctrineCliController;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Internal\CommitOrderCalculator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Load data fixtures from bundles.
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class LoadDataFixturesDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:data:load')
            ->setDescription('Load data fixtures to your database.')
            ->addOption('fixtures', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY, 'The directory or file to load data fixtures from.')
            ->addOption('append', null, InputOption::PARAMETER_OPTIONAL, 'Whether or not to append the data fixtures.', false)
            ->setHelp(<<<EOT
The <info>doctrine:data:load</info> command loads data fixtures from your bundles:

  <info>./symfony doctrine:data:load</info>

You can also optionally specify the path to fixtures with the <info>--fixtures</info> option:

  <info>./symfony doctrine:data:load --fixtures=/path/to/fixtures1 --fixtures=/path/to/fixtures2</info>

If you want to append the fixtures instead of flushing the database first you can use the <info>--append</info> option:

  <info>./symfony doctrine:data:load --append</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $defaultEm = $this->container->getDoctrine_ORM_EntityManagerService();
        $dirOrFile = $input->getOption('fixtures');
        if ($dirOrFile) {
            $paths = is_array($dirOrFile) ? $dirOrFile : array($dirOrFile);
        } else {
            $paths = array();
            $bundleDirs = $this->container->getKernelService()->getBundleDirs();
            foreach ($this->container->getKernelService()->getBundles() as $bundle) {
                $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
                $namespace = str_replace('/', '\\', dirname($tmp));
                $class = basename($tmp);

                if (isset($bundleDirs[$namespace]) && is_dir($dir = $bundleDirs[$namespace].'/'.$class.'/Resources/data/fixtures/doctrine')) {
                    $paths[] = $dir;
                }
            }
        }

        $files = array();
        foreach ($paths as $path) {
            if (is_dir($path)) {
                $finder = new Finder();
                $found = iterator_to_array($finder
                    ->files()
                    ->name('*.php')
                    ->in($path));
            } else {
                $found = array($path);
            }
            $files = array_merge($files, $found);
        }

        $ems = array();
        $emEntities = array();
        $files = array_unique($files);
        foreach ($files as $file) {
            $em = $defaultEm;
            $output->writeln(sprintf('<info>Loading data fixtures from <comment>"%s"</comment></info>', $file));

            $before = array_keys(get_defined_vars());
            include($file);
            $after = array_keys(get_defined_vars());
            $new = array_diff($after, $before);
            $params = $em->getConnection()->getParams();
            $emName = isset($params['path']) ? $params['path']:$params['dbname'];

            $ems[$emName] = $em;
            $emEntities[$emName] = array();
            $variables = array_values($new);

            foreach ($variables as $variable) {
                $value = $$variable;
                if (!is_object($value) || $value instanceof \Doctrine\ORM\EntityManager) {
                    continue;
                }
                $emEntities[$emName][] = $value;
            }
            foreach ($ems as $emName => $em) {
                if (!$input->getOption('append')) {
                    $output->writeln(sprintf('<info>Purging data from entity manager named <comment>"%s"</comment></info>', $emName));
                    $this->purgeEntityManager($em);
                }

                $entities = $emEntities[$emName];
                $numEntities = count($entities);
                $output->writeln(sprintf('<info>Persisting "%s" '.($numEntities > 1 ? 'entities' : 'entity').'</info>', count($entities)));

                foreach ($entities as $entity) {
                    $output->writeln(sprintf('<info>Persisting "%s" entity:</info>', get_class($entity)));
                    $output->writeln('');
                    $output->writeln(var_dump($entity));

                    $em->persist($entity);
                }
                $output->writeln('<info>Flushing entity manager</info>');
                $em->flush();
            }
        }
    }

    protected function purgeEntityManager(EntityManager $em)
    {
        $classes = array();
        $metadatas = $em->getMetadataFactory()->getAllMetadata();

        foreach ($metadatas as $metadata) {
            if (!$metadata->isMappedSuperclass) {
                $classes[] = $metadata;
            }
        }
        $cmf = $em->getMetadataFactory();
        $classes = $this->getCommitOrder($em, $classes);
        for ($i = count($classes) - 1; $i >= 0; --$i) {
            $class = $classes[$i];
            if ($cmf->hasMetadataFor($class->name)) {
                try {
                    $em->createQuery('DELETE FROM '.$class->name.' a')->execute();
                } catch (Exception $e) {}
            }
        }
    }

    protected function getCommitOrder(EntityManager $em, array $classes)
    {
        $calc = new CommitOrderCalculator;

        foreach ($classes as $class) {
            $calc->addClass($class);

            foreach ($class->associationMappings as $assoc) {
                if ($assoc->isOwningSide) {
                    $targetClass = $em->getClassMetadata($assoc->targetEntityName);

                    if ( ! $calc->hasClass($targetClass->name)) {
                            $calc->addClass($targetClass);
                    }

                    // add dependency ($targetClass before $class)
                    $calc->addDependency($targetClass, $class);
                }
            }
        }

        return $calc->getCommitOrder();
    }
}