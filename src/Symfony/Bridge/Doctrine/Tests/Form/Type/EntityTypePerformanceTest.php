<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form\Type;

use Symfony\Component\Form\Test\FormPerformanceTestCase;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Tests\DoctrineOrmTestCase;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class EntityTypePerformanceTest extends FormPerformanceTestCase
{
    const ENTITY_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    protected function getExtensions()
    {
        $manager = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');

        $manager->expects($this->any())
            ->method('getManager')
            ->will($this->returnValue($this->em));

        $manager->expects($this->any())
            ->method('getManagerForClass')
            ->will($this->returnValue($this->em));

        return array(
            new CoreExtension(),
            new DoctrineOrmExtension($manager)
        );
    }

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

        $this->em = DoctrineOrmTestCase::createTestEntityManager();

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(self::ENTITY_CLASS),
        );

        try {
            $schemaTool->dropSchema($classes);
        } catch (\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch (\Exception $e) {
        }

        $ids = range(1, 300);

        foreach ($ids as $id) {
            $name = 65 + chr($id % 57);
            $this->em->persist(new SingleIdentEntity($id, $name));
        }

        $this->em->flush();
    }

    /**
     * This test case is realistic in collection forms where each
     * row contains the same entity field.
     *
     * @group benchmark
     */
    public function testCollapsedEntityField()
    {
        $this->setMaxRunningTime(1);

        for ($i = 0; $i < 40; ++$i) {
            $form = $this->factory->create('entity', null, array(
                'class' => self::ENTITY_CLASS,
            ));

            // force loading of the choice list
            $form->createView();
        }
    }

    /**
     * @group benchmark
     */
    public function testCollapsedEntityFieldWithChoices()
    {
        $choices = $this->em->createQuery('SELECT c FROM '.self::ENTITY_CLASS.' c')->getResult();
        $this->setMaxRunningTime(1);

        for ($i = 0; $i < 40; ++$i) {
            $form = $this->factory->create('entity', null, array(
                'class' => self::ENTITY_CLASS,
                'choices' => $choices,
            ));

            // force loading of the choice list
            $form->createView();
        }
    }

    /**
     * @group benchmark
     */
    public function testCollapsedEntityFieldWithPreferredChoices()
    {
        $choices = $this->em->createQuery('SELECT c FROM '.self::ENTITY_CLASS.' c')->getResult();
        $this->setMaxRunningTime(1);

        for ($i = 0; $i < 40; ++$i) {
            $form = $this->factory->create('entity', null, array(
                    'class' => self::ENTITY_CLASS,
                    'preferred_choices' => $choices,
                ));

            // force loading of the choice list
            $form->createView();
        }
    }
}
