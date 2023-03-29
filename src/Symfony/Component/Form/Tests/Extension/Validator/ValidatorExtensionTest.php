<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Form\Extension\Validator\Constraints\Form as FormConstraint;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\Form;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\TraversalStrategy;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Validation;

class ValidatorExtensionTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @group legacy
     */
    public function testLegacy2Dot5ValidationApi()
    {
        $this->expectDeprecation('Since symfony/form 6.3: The signature of "Symfony\Component\Form\Extension\Validator\ValidatorExtension" constructor requires 3 arguments: "ValidatorInterface $validator, FormRendererInterface $formRenderer = null, TranslatorInterface $translator = null". Passing argument $legacyErrorMessages is deprecated.');

        $metadata = new ClassMetadata(Form::class);

        $metadataFactory = new FakeMetadataFactory();
        $metadataFactory->addMetadata($metadata);

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $extension = new ValidatorExtension($validator, false);

        $this->assertInstanceOf(ValidatorTypeGuesser::class, $extension->loadTypeGuesser());

        $this->assertCount(1, $metadata->getConstraints());
        $this->assertInstanceOf(FormConstraint::class, $metadata->getConstraints()[0]);

        $this->assertSame(CascadingStrategy::NONE, $metadata->cascadingStrategy);
        $this->assertSame(TraversalStrategy::NONE, $metadata->traversalStrategy);
        $this->assertCount(0, $metadata->getPropertyMetadata('children'));
    }

    /**
     * @group legacy
     */
    public function testLegacyWithBadFormRendererType()
    {
        $metadata = new ClassMetadata(Form::class);

        $metadataFactory = new FakeMetadataFactory();
        $metadataFactory->addMetadata($metadata);

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument 2 passed to "Symfony\Component\Form\Extension\Validator\ValidatorExtension::__construct()" must be an instance of "Symfony\Component\Form\FormRendererInterface" or null, "stdClass" given.');

        new ValidatorExtension($validator, new \stdClass());
    }

    /**
     * @group legacy
     */
    public function testLegacyWithBadTranslatorType()
    {
        $metadata = new ClassMetadata(Form::class);

        $metadataFactory = new FakeMetadataFactory();
        $metadataFactory->addMetadata($metadata);

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument 3 passed to "Symfony\Component\Form\Extension\Validator\ValidatorExtension::__construct()" must be an instance of "Symfony\Contracts\Translation\TranslatorInterface" or null, "stdClass" given.');

        new ValidatorExtension($validator, null, new \stdClass());
    }

    public function test2Dot5ValidationApi()
    {
        $metadata = new ClassMetadata(Form::class);

        $metadataFactory = new FakeMetadataFactory();
        $metadataFactory->addMetadata($metadata);

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $extension = new ValidatorExtension($validator);

        $this->assertInstanceOf(ValidatorTypeGuesser::class, $extension->loadTypeGuesser());

        $this->assertCount(1, $metadata->getConstraints());
        $this->assertInstanceOf(FormConstraint::class, $metadata->getConstraints()[0]);

        $this->assertSame(CascadingStrategy::NONE, $metadata->cascadingStrategy);
        $this->assertSame(TraversalStrategy::NONE, $metadata->traversalStrategy);
        $this->assertCount(0, $metadata->getPropertyMetadata('children'));
    }
}
