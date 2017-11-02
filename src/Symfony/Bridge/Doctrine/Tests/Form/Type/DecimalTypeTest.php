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

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bridge\Doctrine\Form\Type\DecimalType;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\Price;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Tests\Extension\Core\Type\BaseTypeTest;

class DecimalTypeTest extends BaseTypeTest
{
    /**
     * @var string
     */
    const TESTED_TYPE = DecimalType::class;

    /**
     * @var EntityManager
     */
    private $em;

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();

        parent::setUp();

        $schemaTool = new SchemaTool($this->em);
        $classes = array(
            $this->em->getClassMetadata(Price::class)
        );

        try {
            $schemaTool->dropSchema($classes);
        } catch (\Exception $e) {
        }

        try {
            $schemaTool->createSchema($classes);
        } catch (\Exception $e) {
        }
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->em = null;
    }

    public function testSubmitWithSameStringValue()
    {
        $price = new Price(1, 1.23);
        $this->em->persist($price);
        $this->em->flush();

        $this->em->refresh($price);

        $this->assertInternalType('string', $price->value);
        $stringValue = $price->value;

        $formBuilder = $this->factory->createBuilder(FormType::class, $price, array(
            'data_class' => Price::class
        ));
        $formBuilder->add('value', static::TESTED_TYPE);

        $form = $formBuilder->getForm();
        $form->submit(array(
            'value' => $stringValue
        ));

        $this->assertSame($stringValue, $price->value);

        $unitOfWork = $this->em->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        $this->assertSame(array(), $unitOfWork->getEntityChangeSet($price));
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
