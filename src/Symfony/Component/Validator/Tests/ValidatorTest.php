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

use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Validator\AbstractLegacyApiTest;
use Symfony\Component\Validator\Validator as LegacyValidator;

class ValidatorTest extends AbstractLegacyApiTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory)
    {
        return new LegacyValidator($metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testValidateValueRejectsValid()
    {
        $this->validator->validateValue(new Entity(), new Valid());
    }
}
