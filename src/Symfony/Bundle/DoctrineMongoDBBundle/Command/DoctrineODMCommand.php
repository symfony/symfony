<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Doctrine\ODM\MongoDB\Tools\Console\Helper\DocumentManagerHelper;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Doctrine\ODM\MongoDB\Tools\DisconnectedClassMetadataFactory;
use Doctrine\ODM\MongoDB\Tools\DocumentGenerator;

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

    protected function getDocumentGenerator()
    {
        $documentGenerator = new DocumentGenerator();
        $documentGenerator->setAnnotationPrefix('mongodb:');
        $documentGenerator->setGenerateAnnotations(false);
        $documentGenerator->setGenerateStubMethods(true);
        $documentGenerator->setRegenerateDocumentIfExists(false);
        $documentGenerator->setUpdateDocumentIfExists(true);
        $documentGenerator->setNumSpaces(4);
        return $documentGenerator;
    }

    protected function getDoctrineDocumentManagers()
    {
        $documentManagerNames = $this->container->getParameter('doctrine.odm.mongodb.document_managers');
        $documentManagers = array();
        foreach ($documentManagerNames as $documentManagerName) {
            $dm = $this->container->get(sprintf('doctrine.odm.mongodb.%s_document_manager', $documentManagerName));
            $documentManagers[] = $dm;
        }
        return $documentManagers;
    }

    protected function getBundleMetadatas(Bundle $bundle)
    {
        $namespace = $bundle->getNamespace();
        $bundleMetadatas = array();
        $documentManagers = $this->getDoctrineDocumentManagers();
        foreach ($documentManagers as $key => $dm) {
            $cmf = new DisconnectedClassMetadataFactory();
            $cmf->setDocumentManager($dm);
            $cmf->setConfiguration($dm->getConfiguration());
            $metadatas = $cmf->getAllMetadata();
            foreach ($metadatas as $metadata) {
                if (strpos($metadata->name, $namespace) === 0) {
                    $bundleMetadatas[$metadata->name] = $metadata;
                }
            }
        }

        return $bundleMetadatas;
    }

    protected function findBundle($bundleName)
    {
        $foundBundle = false;
        foreach ($this->getApplication()->getKernel()->getBundles() as $bundle) {
            /* @var $bundle Bundle */
            if (strtolower($bundleName) == strtolower($bundle->getName())) {
                $foundBundle = $bundle;
                break;
            }
        }

        if (!$foundBundle) {
            throw new \InvalidArgumentException("No bundle " . $bundleName . " was found.");
        }

        return $foundBundle;
    }

    /**
     * Transform classname to a path $foundBundle substract it to get the destination
     *
     * @param Bundle $bundle
     * @return string
     */
    protected function findBasePathForBundle($bundle)
    {
        $path = str_replace('\\', '/', $bundle->getNamespace());
        $search = str_replace('\\', '/', $bundle->getPath());
        $destination = str_replace('/'.$path, '', $search, $c);

        if ($c != 1) {
            throw new \RuntimeException(sprintf('Can\'t find base path for bundle (path: "%s", destination: "%s").', $path, $destination));
        }

        return $destination;
    }
}