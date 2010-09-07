<?php

namespace Symfony\Bundle\DoctrineMongoDBBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;

/**
 * Base class for Doctrine ODM console commands to extend.
 *
 * @author     Justin Hileman <justin@shopopensky.com>
 */
abstract class DoctrineODMCommand extends Command
{
    public static function setApplicationDocumentManager(Application $application, $dmName)
    {
        $container = $application->getKernel()->getContainer();
        $dmName = $dmName ? $dmName : 'default';
        $dmServiceName = sprintf('doctrine.odm.mongodb.%s_document_manager', $dmName);
        if (!$container->has($dmServiceName)) {
            throw new \InvalidArgumentException(sprintf('Could not find Doctrine ODM DocumentManager named "%s"', $dmName));
        }

        $dm = $container->get($dmServiceName);
        $helperSet = $application->getHelperSet();
        $helperSet->set(new DocumentManagerHelper($dm), 'dm');
    }
}
