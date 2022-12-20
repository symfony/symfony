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
    public function test2Dot5ValidationApi()
    {
        $metadata = new ClassMetadata(Form::class);

        $metadataFactory = new FakeMetadataFactory();
        $metadataFactory->addMetadata($metadata);

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory($metadataFactory)
            ->getValidator();

        $extension = new ValidatorExtension($validator, false);

        self::assertInstanceOf(ValidatorTypeGuesser::class, $extension->loadTypeGuesser());

        self::assertCount(1, $metadata->getConstraints());
        self::assertInstanceOf(FormConstraint::class, $metadata->getConstraints()[0]);

        self::assertSame(CascadingStrategy::NONE, $metadata->cascadingStrategy);
        self::assertSame(TraversalStrategy::NONE, $metadata->traversalStrategy);
        self::assertCount(0, $metadata->getPropertyMetadata('children'));
    }
}
