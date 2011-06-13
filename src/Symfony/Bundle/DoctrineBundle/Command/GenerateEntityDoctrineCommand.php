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
use Doctrine\ORM\Tools\Export\ClassMetadataExporter;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Initialize a new Doctrine entity inside a bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class GenerateEntityDoctrineCommand extends DoctrineCommand
{
    protected function configure()
    {
        $this
            ->setName('doctrine:generate:entity')
            ->setAliases(array('generate:doctrine:entity'))
            ->setDescription('Generate a new Doctrine entity inside a bundle')
            ->addArgument('entity', InputArgument::REQUIRED, 'The entity class name to initialize (shortcut notation)')
            ->addArgument('fields', InputArgument::OPTIONAL, 'The fields to create with the new entity')
            ->addOption('mapping-type', null, InputOption::VALUE_OPTIONAL, 'The mapping type to to use for the entity', 'yml')
            ->setHelp(<<<EOT
The <info>doctrine:generate:entity</info> task generates a new Doctrine
entity inside a bundle:

<info>./app/console doctrine:generate:entity AcmeBlogBundle:Blog/Post</info>

The above would initialize a new entity in the following entity namespace
<info>Acme\BlogBundle\Entity\Blog\Post</info>.

You can also optionally specify the fields you want to generate in the new
entity:

<info>./app/console doctrine:generate:entity AcmeBlogBundle:Blog/Post "title:string(255) body:text"</info>

By default, the command uses YAML for the mapping information; change it
with <comment>--mapping-type</comment>:

<info>./app/console doctrine:generate:entity AcmeBlogBundle:Blog/Post --mapping-type=annotation</info>
EOT
        );
    }

    /**
     * @throws \InvalidArgumentException When the bundle doesn't end with Bundle (Example: "Bundle/MySampleBundle")
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entity = str_replace('/', '\\', $input->getArgument('entity'));

        if (false === $pos = strpos($entity, ':')) {
            throw new \InvalidArgumentException(sprintf('The entity name must contain a : ("%s" given, expecting something like AcmeBlogBundle:Blog/Post)', $entity));
        }

        $bundleName = substr($entity, 0, $pos);
        $bundle = $this->getApplication()->getKernel()->getBundle($bundleName);

        // we need to create the directory so that the command works even
        // for the very first entity created in the bundle
        if (!is_dir($bundle->getPath().'/Entity')) {
            @mkdir($bundle->getPath().'/Entity', 0777, true);
        }

        $entity = substr($entity, $pos + 1);
        $fullEntityClassName = $this->container->get('doctrine')->getEntityNamespace($bundleName).'\\'.$entity;
        $mappingType = $input->getOption('mapping-type');

        $class = new ClassMetadataInfo($fullEntityClassName);
        $class->mapField(array('fieldName' => 'id', 'type' => 'integer', 'id' => true));
        $class->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_AUTO);

        // Map the specified fields
        if ($fields = $input->getArgument('fields')) {
            $e = explode(' ', $fields);
            foreach ($e as $value) {
                $e = explode(':', $value);
                $name = $e[0];
                if (strlen($name)) {
                    $type = isset($e[1]) ? $e[1] : 'string';
                    preg_match_all('/(.*)\((.*)\)/', $type, $matches);
                    $type = isset($matches[1][0]) ? $matches[1][0] : $type;
                    $length = isset($matches[2][0]) ? $matches[2][0] : null;
                    $class->mapField(array('fieldName' => $name, 'type' => $type, 'length' => $length));
                }
            }
        }

        $entityPath = $bundle->getPath().'/Entity/'.str_replace('\\', '/', $entity).'.php';
        if (file_exists($entityPath)) {
            throw new \RuntimeException(sprintf('Entity "%s" already exists.', $class->name));
        }

        $entityGenerator = $this->getEntityGenerator();
        if ('annotation' === $mappingType) {
            $entityGenerator->setGenerateAnnotations(true);
            $entityCode = $entityGenerator->generateEntityClass($class);
            $mappingPath = $mappingCode = false;
        } else {
            // Setup a new exporter for the mapping type specified
            $cme = new ClassMetadataExporter();
            $exporter = $cme->getExporter($mappingType);
            $mappingType = 'yaml' == $mappingType ? 'yml' : $mappingType;
            $mappingPath = $bundle->getPath().'/Resources/config/doctrine/'.str_replace('\\', '.', $entity).'.orm.'.$mappingType;
            $mappingCode = $exporter->exportClassMetadata($class);

            $entityGenerator->setGenerateAnnotations(false);
            $entityCode = $entityGenerator->generateEntityClass($class);

            if (file_exists($mappingPath)) {
                throw new \RuntimeException(sprintf('Cannot generate entity when mapping "%s" already exists.', $mappingPath));
            }
        }

        $output->writeln(sprintf('Generating entity for "<info>%s</info>"', $bundle->getName()));
        $output->writeln(sprintf('  > entity <comment>%s</comment> into <info>%s</info>', $fullEntityClassName, $entityPath));

        if (!is_dir($dir = dirname($entityPath))) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($entityPath, $entityCode);

        if ($mappingPath) {
            $output->writeln(sprintf('  > mapping into <info>%s</info>', $mappingPath));

            if (!is_dir($dir = dirname($mappingPath))) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($mappingPath, $mappingCode);
        }
    }
}
