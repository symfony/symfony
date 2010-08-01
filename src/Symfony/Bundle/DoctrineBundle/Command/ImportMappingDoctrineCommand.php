<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\ConvertMappingCommand;
use Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper;
use Doctrine\ORM\Tools\Console\Helper\EntityManagerHelper;
use Doctrine\ORM\Mapping\Driver\DatabaseDriver;
use Doctrine\ORM\Tools\DisconnectedClassMetadataFactory;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Import Doctrine ORM metadata mapping information from an existing database.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class ImportMappingDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:mapping:import')
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to import the mapping information to.')
            ->addArgument('mapping-type', InputArgument::OPTIONAL, 'The mapping type to export the imported mapping information to.')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setDescription('Import mapping information from an existing database.')
            ->setHelp(<<<EOT
The <info>doctrine:mapping:import</info> command imports mapping information from an existing database:

  <info>./symfony doctrine:mapping:import "Bundle\MyCustomBundle" xml</info>

You can also optionally specify which entity manager to import from with the <info>--em</info> option:

  <info>./symfony doctrine:mapping:import "Bundle\MyCustomBundle" xml --em=default</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundleClass = null;
        $bundleDirs = $this->container->getKernelService()->getBundleDirs();
        foreach ($this->container->getKernelService()->getBundles() as $bundle) {
            if (strpos(get_class($bundle), $input->getArgument('bundle')) !== false) {
                $tmp = dirname(str_replace('\\', '/', get_class($bundle)));
                $namespace = str_replace('/', '\\', dirname($tmp));
                $class = basename($tmp);

                if (isset($bundleDirs[$namespace])) {
                    $destPath = realpath($bundleDirs[$namespace]).'/'.$class;
                    $bundleClass = $class;
                    break;
                }
            }
        }

        $type = $input->getArgument('mapping-type') ? $input->getArgument('mapping-type') : 'xml';
        if ($type === 'annotation') {
            $destPath .= '/Entities';
        } else {
            $destPath .= '/Resources/config/doctrine/metadata';
        }

        $cme = new ClassMetadataExporter();
        $exporter = $cme->getExporter($type);

        if ($type === 'annotation') {
            $entityGenerator = $this->getEntityGenerator();
            $exporter->setEntityGenerator($entityGenerator);
        }

        $emName = $input->getOption('em') ? $input->getOption('em') : 'default';
        $emServiceName = sprintf('doctrine.orm.%s_entity_manager', $emName);
        $em = $this->container->get($emServiceName);
        $databaseDriver = new DatabaseDriver($em->getConnection()->getSchemaManager());
        $em->getConfiguration()->setMetadataDriverImpl($databaseDriver);

        $cmf = new DisconnectedClassMetadataFactory($em);
        $metadata = $cmf->getAllMetadata();
        if ($metadata) {
            $output->writeln(sprintf('Importing mapping information from "<info>%s</info>" entity manager', $emName));
            foreach ($metadata as $class) {
                $className = $class->name;
                $class->name = $namespace.'\\'.$bundleClass.'\\Entities\\'.$className;
                if ($type === 'annotation') {
                    $path = $destPath.'/'.$className.'.php';
                } else {
                    $path = $destPath.'/'.str_replace('\\', '.', $class->name).'.dcm.xml';
                }
                $output->writeln(sprintf('  > writing <comment>%s</comment>', $path));
                $code = $exporter->exportClassMetadata($class);
                if (!is_dir($dir = dirname($path))) {
                    mkdir($dir, 0777, true);
                }
                file_put_contents($path, $code);
            }
        } else {
            $output->writeln('Database does not have any mapping information.'.PHP_EOL, 'ERROR');
        }
    }
}