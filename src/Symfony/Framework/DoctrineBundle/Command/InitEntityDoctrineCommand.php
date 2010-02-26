<?php

namespace Symfony\Framework\DoctrineBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Framework\WebBundle\Util\Filesystem;
use Doctrine\Common\Cli\Configuration;
use Doctrine\Common\Cli\CliController as DoctrineCliController;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Initialize a new Doctrine entity inside a bundle.
 *
 * @package    symfony
 * @subpackage console
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class InitEntityDoctrineCommand extends DoctrineCommand
{
  /**
   * @see Command
   */
  protected function configure()
  {
    $this
      ->setName('doctrine:init-entity')
      ->setDescription('Initialize a new Doctrine entity inside a bundle.')
      ->addOption('bundle', null, InputOption::PARAMETER_REQUIRED, 'The bundle to initialize the entity in.')
      ->addOption('entity', null, InputOption::PARAMETER_REQUIRED, 'The entity class to initialize.')
      ->setHelp('
The <info>doctrine:init-entity</info> task initializes a new Doctrine entity inside a bundle:

    <comment>php console doctrine:init-entity --bundle="Bundle\MyCustomBundle" --entity="User\Group"</comment>

The above would initialize a new entity in the following entity namespace <info>Bundle\MyCustomBundle\Entities\User\Group</info>.

You can now build your entities and update your database schema:

    <comment>php console doctrine:build --entities --and-update-schema</comment>

Now you have a new entity and your database has been updated.
      ')
    ;
  }

  /**
   * @see Command
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    if (!preg_match('/Bundle$/', $bundle = $input->getOption('bundle')))
    {
      throw new \InvalidArgumentException('The bundle name must end with Bundle. Example: "Bundle\MySampleBundle".');
    }

    $dirs = $this->container->getKernelService()->getBundleDirs();

    $tmp = str_replace('\\', '/', $bundle);
    $namespace = str_replace('/', '\\', dirname($tmp));
    $bundle = basename($tmp);

    if (!isset($dirs[$namespace]))
    {
      throw new \InvalidArgumentException(sprintf('Unable to initialize the bundle entity (%s not defined).', $namespace));
    }

    $entity = $input->getOption('entity');
    $entityNamespace = $namespace.'\\'.$bundle.'\\Entities';
    $fullEntityClassName = $entityNamespace.'\\'.$entity;
    $tmp = str_replace('\\', '/', $fullEntityClassName);
    $tmp = str_replace('/', '\\', dirname($tmp));
    $className = basename($tmp);

    $extends = null;
    $path = $dirs[$namespace].'/'.$bundle.'/Resources/config/doctrine/metadata/'.str_replace('\\', '.', $fullEntityClassName).'.dcm.xml';

    $xml = sprintf('<?xml version="1.0" encoding="UTF-8"?>

<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://doctrine-project.org/schemas/orm/doctrine-mapping
                          http://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="%s" table="%s">
        <id name="id" type="integer" column="id">
            <generator strategy="AUTO"/>
        </id>
    </entity>

</doctrine-mapping>',
    $fullEntityClassName,
    str_replace('\\', '_', strtolower($entity))
  );

    if (!is_dir($dir = dirname($path)))
    {
      mkdir($dir, 0777, true);
    }

    file_put_contents($path, $xml);
    $this->runCommand('doctrine:build-entities');
  }
}