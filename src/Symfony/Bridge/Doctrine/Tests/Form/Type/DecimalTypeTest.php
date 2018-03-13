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

    // On some platforms, fetched decimal values are rounded (the full scale is not preserved)
    // eg : on SQLite, inserted float value 4.50 will be fetched as string value "4.5"
    public function testSubmitWithSameStringValueOnAPlatformThatDoesNotPreserveFullScaleValueWithoutForceFullScale()
    {
        $fullScalePrice = new Price(1, 1.23);
        $nonFullScalePrice = new Price(2, 4.50);
        $this->em->persist($fullScalePrice);
        $this->em->persist($nonFullScalePrice);
        $this->em->flush();

        $this->em->refresh($fullScalePrice);
        $this->em->refresh($nonFullScalePrice);

        $this->assertInternalType('string', $fullScalePrice->doesNotPreserveFullScaleValue);
        $fullScalePriceStringValue = $fullScalePrice->doesNotPreserveFullScaleValue;

        $formBuilder = $this->factory->createBuilder(FormType::class, $fullScalePrice, array(
            'data_class' => Price::class
        ));
        $formBuilder->add('doesNotPreserveFullScaleValue', static::TESTED_TYPE, array(
            'force_full_scale' => false
        ));

        $form = $formBuilder->getForm();
        $form->submit(array(
            'doesNotPreserveFullScaleValue' => $fullScalePriceStringValue
        ));

        $this->assertSame($fullScalePriceStringValue, $fullScalePrice->doesNotPreserveFullScaleValue);

        $this->assertInternalType('string', $nonFullScalePrice->doesNotPreserveFullScaleValue);
        $nonFullScalePriceStringValue = $nonFullScalePrice->doesNotPreserveFullScaleValue;

        $formBuilder = $this->factory->createBuilder(FormType::class, $nonFullScalePrice, array(
            'data_class' => Price::class
        ));
        $formBuilder->add('doesNotPreserveFullScaleValue', static::TESTED_TYPE, array(
            'force_full_scale' => false
        ));

        $form = $formBuilder->getForm();
        $form->submit(array(
            'doesNotPreserveFullScaleValue' => $nonFullScalePriceStringValue
        ));

        $this->assertSame($nonFullScalePriceStringValue, $nonFullScalePrice->doesNotPreserveFullScaleValue);

        $unitOfWork = $this->em->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        $this->assertSame(array(), $unitOfWork->getEntityChangeSet($fullScalePrice));
        $this->assertSame(array(), $unitOfWork->getEntityChangeSet($nonFullScalePrice));
    }

    // On some platforms, fetched decimal values are not rounded at all (the full scale is preserved)
    // eg : on PostgreSQL, inserted float value 4.50 will be fetched as string value "4.50"
    public function testSubmitWithSameStringValueOnAPlatformThatPreserveFullScaleValueWithForceFullScale()
    {
        $fullScalePrice = new Price(1, 1.23);
        $nonFullScalePrice = new Price(2, 4.50);
        $this->em->persist($fullScalePrice);
        $this->em->persist($nonFullScalePrice);
        $this->em->flush();

        $this->em->refresh($fullScalePrice);
        $this->em->refresh($nonFullScalePrice);

        $this->assertInternalType('string', $fullScalePrice->preserveFullScaleValueSimulation);
        $fullScalePriceStringValue = $fullScalePrice->preserveFullScaleValueSimulation;

        $formBuilder = $this->factory->createBuilder(FormType::class, $fullScalePrice, array(
            'data_class' => Price::class
        ));
        $formBuilder->add('preserveFullScaleValueSimulation', static::TESTED_TYPE, array(
            'force_full_scale' => true,
            'scale' => 2
        ));

        $form = $formBuilder->getForm();
        $form->submit(array(
            'preserveFullScaleValueSimulation' => $fullScalePriceStringValue
        ));

        $this->assertSame($fullScalePriceStringValue, $fullScalePrice->preserveFullScaleValueSimulation);

        $this->assertInternalType('string', $nonFullScalePrice->preserveFullScaleValueSimulation);
        $nonFullScalePriceStringValue = $nonFullScalePrice->preserveFullScaleValueSimulation;

        $formBuilder = $this->factory->createBuilder(FormType::class, $nonFullScalePrice, array(
            'data_class' => Price::class
        ));
        $formBuilder->add('preserveFullScaleValueSimulation', static::TESTED_TYPE, array(
            'force_full_scale' => true,
            'scale' => 2
        ));

        $form = $formBuilder->getForm();
        $form->submit(array(
            'preserveFullScaleValueSimulation' => $nonFullScalePriceStringValue
        ));

        $this->assertSame($nonFullScalePriceStringValue, $nonFullScalePrice->preserveFullScaleValueSimulation);

        $unitOfWork = $this->em->getUnitOfWork();
        $unitOfWork->computeChangeSets();

        $this->assertSame(array(), $unitOfWork->getEntityChangeSet($fullScalePrice));
        $this->assertSame(array(), $unitOfWork->getEntityChangeSet($nonFullScalePrice));
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
