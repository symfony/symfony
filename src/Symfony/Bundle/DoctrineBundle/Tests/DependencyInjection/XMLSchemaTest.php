<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\DoctrineBundle\Tests\DependencyInjection;

class XMLSchemaTest extends \PHPUnit_Framework_TestCase
{
    static public function dataValidateSchemaFiles()
    {
        $schemaFiles = array();
        $di = new \DirectoryIterator(__DIR__."/Fixtures/config/xml");
        foreach ($di as $element) {
            if ($element->isFile() && strpos($element->getFilename(), ".xml") !== false) {
                $schemaFiles[] = array($element->getPathname());
            }
        }

        return $schemaFiles;
    }

    /**
     * @dataProvider dataValidateSchemaFiles
     */
    public function testValidateSchema($file)
    {
        $found = false;
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->load($file);


        $dbalElements = $dom->getElementsByTagNameNS("http://symfony.com/schema/dic/doctrine", "config");
        if ($dbalElements->length) {
            $dbalDom = new \DOMDocument('1.0', 'UTF-8');
            $dbalNode = $dbalDom->importNode($dbalElements->item(0));
            $dbalDom->appendChild($dbalNode);

            $ret = $dbalDom->schemaValidate(__DIR__."/../../Resources/config/schema/doctrine-1.0.xsd");
            $this->assertTrue($ret, "DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.");
            $found = true;
        }

        $ormElements = $dom->getElementsByTagNameNS("http://symfony.com/schema/dic/doctrine", "config");
        if ($ormElements->length) {
            $ormDom = new \DOMDocument('1.0', 'UTF-8');
            $ormNode = $ormDom->importNode($ormElements->item(0));
            $ormDom->appendChild($ormNode);

            $ret = $ormDom->schemaValidate(__DIR__."/../../Resources/config/schema/doctrine-1.0.xsd");
            $this->assertTrue($ret, "DoctrineBundle Dependency Injection XMLSchema did not validate this XML instance.");
            $found = true;
        }

        $this->assertTrue($found, "Neither <doctrine:orm> nor <doctrine:dbal> elements found in given XML. Are namespaces configured correctly?");
    }
}
