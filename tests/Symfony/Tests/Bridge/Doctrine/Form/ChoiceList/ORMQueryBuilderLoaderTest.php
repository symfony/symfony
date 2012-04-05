<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Form\ChoiceList;

require_once __DIR__.'/../../DoctrineOrmTestCase.php';
require_once __DIR__.'/../../Fixtures/ItemGroupEntity.php';
require_once __DIR__.'/../../Fixtures/SingleIdentEntity.php';
require_once __DIR__.'/../../Fixtures/NoToStringSingleIdentEntity.php';

use Symfony\Tests\Bridge\Doctrine\DoctrineOrmTestCase;
use Symfony\Tests\Bridge\Doctrine\Fixtures\ItemGroupEntity;
use Symfony\Tests\Bridge\Doctrine\Fixtures\SingleIdentEntity;
use Symfony\Tests\Bridge\Doctrine\Fixtures\NoToStringSingleIdentEntity;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Symfony\Bridge\Doctrine\Form\ChoiceList\ORMQueryBuilderLoader;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Doctrine\ORM\Tools\SchemaTool;

class ORMQueryBuilderLoaderTest extends DoctrineOrmTestCase
{
    const SINGLE_IDENT_CLASS = 'Symfony\Tests\Bridge\Doctrine\Fixtures\SingleIdentEntity';

    public function testValues()
    {
        $class = self::SINGLE_IDENT_CLASS;

        $em = $this->createTestEntityManager();
        $this->createSchema($em,$class);

        $entity = new $class(1, 1, 'user1');

        $em->persist($entity);
        $em->flush();

        $repository = $em->getRepository(self::SINGLE_IDENT_CLASS);

        $loader = new ORMQueryBuilderLoader(
            $repository->createQueryBuilder('o'),
            $em,
            self::SINGLE_IDENT_CLASS
        );

        $this->assertEquals(array($entity), $loader->getEntitiesByIds('id',array(1)));
    }

    public function testEmptyValues()
    {
        $class = self::SINGLE_IDENT_CLASS;
        $em = $this->createTestEntityManager();
        $this->createSchema($em,$class);

        $repository = $em->getRepository(self::SINGLE_IDENT_CLASS);

        $loader = new ORMQueryBuilderLoader(
            $repository->createQueryBuilder('o'),
            $em,
            self::SINGLE_IDENT_CLASS
        );

        $this->assertEquals(array(), $loader->getEntitiesByIds('id',array()));
    }

    private function createSchema($em,$class)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(array(
            $em->getClassMetadata($class),
        ));
    }
}
