<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use Doctrine\ORM\Tools\EntityGenerator;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Initialize a new Doctrine entity inside a bundle.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateEntityDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:entity')
            ->setDescription('Generate a new Doctrine entity inside a bundle.')
            ->addArgument('bundle', null, InputArgument::REQUIRED, 'The bundle to initialize the entity in.')
            ->addArgument('entity', null, InputArgument::REQUIRED, 'The entity class to initialize.')
            ->addOption('mapping-type', null, InputOption::PARAMETER_OPTIONAL, 'The mapping type to to use for the entity.')
            ->addOption('fields', null, InputOption::PARAMETER_OPTIONAL, 'The fields to create with the new entity.')
            ->setHelp(<<<EOT
The <info>doctrine:generate:entity</info> task initializes a new Doctrine entity inside a bundle:

  <info>./symfony doctrine:generate:entity "Bundle\MyCustomBundle" "User\Group"</info>

The above would initialize a new entity in the following entity namespace <info>Bundle\MyCustomBundle\Entities\User\Group</info>.

You can also optionally specify the fields you want to generate in the new entity:

  <info>./symfony doctrine:generate:entity "Bundle\MyCustomBundle" "User\Group" --fields="name:string(255) description:text"</info>
EOT
        );
    }

    /**
     * @throws \InvalidArgumentException When the bundle doesn't end with Bundle (Example: "Bundle\MySampleBundle")
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!preg_match('/Bundle$/', $bundle = $input->getArgument('bundle'))) {
            throw new \InvalidArgumentException('The bundle name must end with Bundle. Example: "Bundle\MySampleBundle".');
        }

        $dirs = $this->container->getKernelService()->getBundleDirs();

        $tmp = str_replace('\\', '/', $bundle);
        $namespace = str_replace('/', '\\', dirname($tmp));
        $bundle = basename($tmp);

        if (!isset($dirs[$namespace])) {
            throw new \InvalidArgumentException(sprintf('Unable to initialize the bundle entity (%s not defined).', $namespace));
        }

        $entity = $input->getArgument('entity');
        $entityNamespace = $namespace.'\\'.$bundle.'\\Entities';
        $fullEntityClassName = $entityNamespace.'\\'.$entity;
        $tmp = str_replace('\\', '/', $fullEntityClassName);
        $tmp = str_replace('/', '\\', dirname($tmp));
        $className = basename($tmp);
        $mappingType = $input->getOption('mapping-type');
        $mappingType = $mappingType ? $mappingType : 'xml';

        $class = new ClassMetadataInfo($fullEntityClassName);
        $class->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
        $class->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);

        // Map the specified fields
        $fields = $input->getOption('fields');
        if ($fields)
        {
          $e = explode(' ', $fields);
          foreach ($e as $value) {
              $e = explode(':', $value);
              $name = $e[0];
              $type = isset($e[1]) ? $e[1] : 'string';
              preg_match_all('/(.*)\((.*)\)/', $type, $matches);
              $type = isset($matches[1][0]) ? $matches[1][0] : 'string';
              $length = isset($matches[2][0]) ? $matches[2][0] : null;
              $class->mapField(array(
                  'fieldName' => $name,
                  'type' => $type,
                  'length' => $length
              ));
          }
        }

        // Setup a new exporter for the mapping type specified
        $cme = new ClassMetadataExporter();
        $exporter = $cme->getExporter($mappingType);

        if ($mappingType === 'annotation') {
            $path = $dirs[$namespace].'/'.$bundle.'/Entities/'.str_replace($entityNamespace.'\\', null, $fullEntityClassName).'.php';

            $exporter->setEntityGenerator($this->getEntityGenerator());
        } else {
            $mappingType = $mappingType == 'yaml' ? 'yml' : $mappingType;
            $path = $dirs[$namespace].'/'.$bundle.'/Resources/config/doctrine/metadata/'.str_replace('\\', '.', $fullEntityClassName).'.dcm.'.$mappingType;
        }

        $code = $exporter->exportClassMetadata($class);

        if (!is_dir($dir = dirname($path))) {
            mkdir($dir, 0777, true);
        }

        $output->writeln(sprintf('Generating entity for "<info>%s</info>"', $bundle));
        $output->writeln(sprintf('  > generating <comment>%s</comment>', $fullEntityClassName));
        file_put_contents($path, $code);
    }
}