<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\ChoiceList;

use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Form\Tests\Extension\Core\ChoiceList\AbstractChoiceListTest;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractEntityChoiceListTest extends AbstractChoiceListTest
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    protected $obj1;

    protected $obj2;

    protected $obj3;

    protected $obj4;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\Form\Form')) {
            $this->markTestSkipped('The "Form" component is not available');
        }

        if (!class_exists('Doctrine\DBAL\Platforms\MySqlPlatform')) {
            $this->markTestSkipped('Doctrine DBAL is not available.');
        }

        if (!class_exists('Doctrine\Common\Version')) {
            $this->markTestSkipped('Doctrine Common is not available.');
        }

        if (!class_exists('Doctrine\ORM\EntityManager')) {
            $this->markTestSkipped('Doctrine ORM is not available.');
        }

        $this->em = DoctrineTestHelper::createTestEntityManager();

        $schemaTool = new SchemaTool($this->em);
        $classes = array($this->em->getClassMetadata($this->getEntityClass()));

        try {
            $schemaTool->dropSchema($classes);
        } catch (\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch (\Exception $e) {
        }

        list($this->obj1, $this->obj2, $this->obj3, $this->obj4) = $this->createObjects();

        $this->em->persist($this->obj1);
        $this->em->persist($this->obj2);
        $this->em->persist($this->obj3);
        $this->em->persist($this->obj4);
        $this->em->flush();

        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->em = null;
    }

    abstract protected function getEntityClass();

    abstract protected function createObjects();

    /**
     * @return \Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface
     */
    protected function createChoiceList()
    {
        return new EntityChoiceList($this->em, $this->getEntityClass());
    }
}
