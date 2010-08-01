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
use Doctrine\ORM\Tools\ConvertDoctrine1Schema;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Convert a Doctrine 1 schema to Doctrine 2 mapping files
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class ConvertDoctrine1SchemaDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:mapping:convert-d1-schema')
            ->setDescription('Convert a Doctrine 1 schema to Doctrine 2 mapping files.')
            ->addArgument('d1-schema', InputArgument::REQUIRED, 'Path to the Doctrine 1 schema files.')
            ->addArgument('bundle', InputArgument::REQUIRED, 'The bundle to write the converted mapping information to.')
            ->addArgument('mapping-type', InputArgument::OPTIONAL, 'The mapping type to export the converted mapping information to.')
            ->setHelp(<<<EOT
The <info>doctrine:mapping:convert-d1-schema</info> command converts a Doctrine 1 schema to Doctrine 2 mapping files:

  <info>./symfony doctrine:mapping:convert-d1-schema /path/to/doctrine1schema "Bundle\MyBundle" xml</info>

Each Doctrine 1 model will have its own XML mapping file located in <info>Bundle/MyBundle/config/doctrine/metadata</info>.
EOT
        );
    }

    /**
     * @see Command
     */
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

        // adjust so file naming works
        if ($type === 'yaml') {
            $type = 'yml';
        }

        $cme = new ClassMetadataExporter();
        $exporter = $cme->getExporter($type);

        if ($type === 'annotation') {
            $entityGenerator = $this->getEntityGenerator();
            $exporter->setEntityGenerator($entityGenerator);
        }

        $converter = new ConvertDoctrine1Schema($input->getArgument('d1-schema'));
        $metadata = $converter->getMetadata();

        if ($metadata) {
            $output->writeln(sprintf('Converting Doctrine 1 schema "<info>%s</info>"', $input->getArgument('d1-schema')));
            foreach ($metadata as $class) {
                $className = $class->name;
                $class->name = $namespace.'\\'.$bundleClass.'\\Entities\\'.$className;
                if ($type === 'annotation') {
                    $path = $destPath.'/'.$className.'.php';
                } else {
                    $path = $destPath.'/'.str_replace('\\', '.', $class->name).'.dcm.'.$type;
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
