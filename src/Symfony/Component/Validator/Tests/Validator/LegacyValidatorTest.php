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

use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Validator as LegacyValidator;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactory;

class LegacyValidatorTest extends AbstractValidatorTest
{
    protected function createValidator(MetadataFactoryInterface $metadataFactory)
    {
        return new LegacyValidator($metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
    }

    public function testNoDuplicateValidationIfConstraintInMultipleGroups()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testGroupSequenceAbortsAfterFailedGroup()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testGroupSequenceIncludesReferences()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testValidateInContext()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testValidateArrayInContext()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testValidateInSeparateContext()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testArray()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testRecursiveArray()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testTraversableTraverseEnabled()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testRecursiveTraversableRecursiveTraversalDisabled()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testRecursiveTraversableRecursiveTraversalEnabled()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testExpectTraversableIfTraverse()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    public function testExpectTraversableIfTraverseOnClass()
    {
        $this->markTestSkipped('Not supported in the legacy API');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testValidateValueRejectsValid()
    {
        $this->validator->validateValue(new Entity(), new Valid());
    }
}
