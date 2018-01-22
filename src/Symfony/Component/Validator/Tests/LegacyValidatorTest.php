<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Validator\AbstractLegacyApiTest;
use Symfony\Component\Validator\Validator as LegacyValidator;

/**
 * @group legacy
 */
class LegacyValidatorTest extends AbstractLegacyApiTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory, array $objectInitializers = array())
    {
        $translator = new IdentityTranslator();
        $translator->setLocale('en');

        return new LegacyValidator($metadataFactory, new ConstraintValidatorFactory(), $translator, 'validators', $objectInitializers);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testValidateValueRejectsValid()
    {
        $this->validator->validateValue(new Entity(), new Valid());
    }

    public function testValidateMultipleGroupsForCollectionConstraint()
    {
        $entity = new Entity();
        $entity->firstName = array('baz' => 2);

        $this->metadata->addPropertyConstraint('firstName', new Collection(
            array(
                'fields' => array(
                    'baz' => array(
                        new Range(array('min' => 3, 'minMessage' => 'Group foo', 'groups' => 'foo')),
                        new Range(array('min' => 5, 'minMessage' => 'Group bar', 'groups' => 'bar')),
                    ),
                ),
            )
        ));

        $violations = $this->validate($entity, null, array('foo', 'bar'));

        $this->assertCount(2, $violations);
        $this->assertSame('Group foo', $violations[0]->getMessage());
        $this->assertSame('Group bar', $violations[1]->getMessage());
    }

    public function testValidateMultipleGroupsForAllConstraint()
    {
        $entity = new Entity();
        $entity->firstName = array(1);

        $this->metadata->addPropertyConstraint('firstName', new All(
            array(
                'constraints' => array(
                    new Range(array('min' => 3, 'minMessage' => 'Group foo', 'groups' => 'foo')),
                    new Range(array('min' => 5, 'minMessage' => 'Group bar', 'groups' => 'bar')),
                ),
            )
        ));

        $violations = $this->validate($entity, null, array('foo', 'bar'));

        $this->assertCount(2, $violations);
        $this->assertSame('Group foo', $violations[0]->getMessage());
        $this->assertSame('Group bar', $violations[1]->getMessage());
    }
}
