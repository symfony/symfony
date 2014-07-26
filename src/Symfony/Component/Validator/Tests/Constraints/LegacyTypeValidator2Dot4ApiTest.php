<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Validation;

/**
 * @since  2.5.3
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LegacyTypeValidator2Dot4ApiTest extends TypeValidatorTest
{
    /**
     * PhpUnit calls data providers of test suites before launching the test
     * suite. If this property is not replicated in every test class, only one
     * file will ever be created and stored in TypeValidatorTest::$file. After
     * the execution of the first TypeValidator test case, tearDownAfterClass()
     * is called and closes the file. Hence the resource is not available
     * anymore in the other TypeValidator test cases.
     */
    protected static $file;

    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_4;
    }
}
