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

use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactory;

class ValidatorTest extends AbstractValidatorTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory)
    {
        return new Validator($metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
    }
}
