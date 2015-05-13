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
use Symfony\Component\Validator\Context\LegacyExecutionContextFactory;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Validator\LegacyValidator;

/**
 * @group legacy
 */
class LegacyValidatorLegacyApiTest extends AbstractLegacyApiTest
{
    protected function setUp()
    {
        if (PHP_VERSION_ID < 50309) {
            $this->markTestSkipped('Not supported prior to PHP 5.3.9');
        }

        parent::setUp();
    }

    protected function createValidator(MetadataFactoryInterface $metadataFactory, array $objectInitializers = array())
    {
        $contextFactory = new LegacyExecutionContextFactory($metadataFactory, new DefaultTranslator());
        $validatorFactory = new ConstraintValidatorFactory();

        return new LegacyValidator($contextFactory, $metadataFactory, $validatorFactory, $objectInitializers);
    }
}
