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

use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmExtension;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Test\FormPerformanceTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class EntityTypePerformanceTest extends FormPerformanceTestCase
{
    const ENTITY_CLASS = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity';

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $em;

    protected static $supportedFeatureSetVersion = 304;

    protected function getExtensions()
    {
        $manager = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();

        $manager->expects($this->any())
            ->method('getManager')
            ->will($this->returnValue($this->em));

        $manager->expects($this->any())
            ->method('getManagerForClass')
            ->will($this->returnValue($this->em));

        return [
            new CoreExtension(),
            new DoctrineOrmExtension($manager),
        ];
    }

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = [
            $this->em->getClassMetadata(self::ENTITY_CLASS),
        ];

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
            $name = 65 + (int) \chr($id % 57);
            $this->em->persist(new SingleIntIdEntity($id, $name));
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
            $form = $this->factory->create('Symfony\Bridge\Doctrine\Form\Type\EntityType', null, [
                'class' => self::ENTITY_CLASS,
            ]);

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
            $form = $this->factory->create('Symfony\Bridge\Doctrine\Form\Type\EntityType', null, [
                'class' => self::ENTITY_CLASS,
                'choices' => $choices,
            ]);

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
            $form = $this->factory->create('Symfony\Bridge\Doctrine\Form\Type\EntityType', null, [
                    'class' => self::ENTITY_CLASS,
                    'preferred_choices' => $choices,
                ]);

            // force loading of the choice list
            $form->createView();
        }
    }
}
