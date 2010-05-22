<?php

namespace Symfony\Framework\DoctrineMigrationsBundle\Command;

use Symfony\Framework\WebBundle\Console\Application;
use Symfony\Framework\DoctrineBundle\Command\DoctrineCommand as BaseCommand;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\Common\Util\Inflector;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Base class for Doctrine console commands to extend from.
 *
 * @package    Symfony
 * @subpackage Framework_DoctrineBundle
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class DoctrineCommand extends BaseCommand
{
    public static function configureMigrationsForBundle(Application $application, $bundle, Configuration $configuration)
    {
        $configuration->setMigrationsNamespace($bundle.'\DoctrineMigrations');
        
        $dirs = $application->getKernel()->getBundleDirs();
        
        $tmp = str_replace('\\', '/', $bundle);
        $namespace = str_replace('/', '\\', dirname($tmp));
        $bundle = basename($tmp);
        
        $dir = $dirs[$namespace].'/'.$bundle.'/DoctrineMigrations';
        $configuration->setMigrationsDirectory($dir);
        $configuration->registerMigrationsFromDirectory($dir);
        $configuration->setName($bundle.' Migrations');
        $configuration->setMigrationsTableName(Inflector::tableize($bundle).'_migration_versions');
    }
}