<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMigrationsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\DoctrineBundle\Command\DoctrineCommand as BaseCommand;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\Common\Util\Inflector;

/**
 * Base class for Doctrine console commands to extend from.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
abstract class DoctrineCommand extends BaseCommand
{
    public static function configureMigrationsForBundle(Application $application, $bundle, Configuration $configuration)
    {
        $bundle = $application->getKernel()->getBundle($bundle);
        $dir = $bundle->getPath().'/DoctrineMigrations';

        $configuration->setMigrationsNamespace($bundle->getNamespace().'\DoctrineMigrations');
        $configuration->setMigrationsDirectory($dir);
        $configuration->registerMigrationsFromDirectory($dir);
        $configuration->setName($bundle->getName().' Migrations');
        $configuration->setMigrationsTableName(Inflector::tableize($bundle->getName()).'_migration_versions');
    }
}
