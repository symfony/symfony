<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Validator;

use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator\RecursiveValidator;

class RecursiveValidator2Dot5ApiTest extends Abstract2Dot5ApiTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory, array $objectInitializers = array())
    {
        $contextFactory = new ExecutionContextFactory(new DefaultTranslator());
        $validatorFactory = new ConstraintValidatorFactory();

        return new RecursiveValidator($contextFactory, $metadataFactory, $validatorFactory, $objectInitializers);
    }
}
