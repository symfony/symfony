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

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\ValidationVisitor;

class LegacyExecutionContextTest extends \PHPUnit_Framework_TestCase
{
    const TRANS_DOMAIN = 'trans_domain';

    private $visitor;
    private $violations;
    private $metadata;
    private $metadataFactory;
    private $globalContext;
    private $translator;

    /**
     * @var ExecutionContext
     */
    private $context;

    protected function setUp()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $this->visitor = $this->getMockBuilder('Symfony\Component\Validator\ValidationVisitor')
            ->disableOriginalConstructor()
            ->getMock();
        $this->violations = new ConstraintViolationList();
        $this->metadata = $this->getMock('Symfony\Component\Validator\MetadataInterface');
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');
        $this->globalContext = $this->getMock('Symfony\Component\Validator\GlobalExecutionContextInterface');
        $this->globalContext->expects($this->any())
            ->method('getRoot')
            ->will($this->returnValue('Root'));
        $this->globalContext->expects($this->any())
            ->method('getViolations')
            ->will($this->returnValue($this->violations));
        $this->globalContext->expects($this->any())
            ->method('getVisitor')
            ->will($this->returnValue($this->visitor));
        $this->globalContext->expects($this->any())
            ->method('getMetadataFactory')
            ->will($this->returnValue($this->metadataFactory));
        $this->translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $this->context = new ExecutionContext($this->globalContext, $this->translator, self::TRANS_DOMAIN, $this->metadata, 'currentValue', 'Group', 'foo.bar');
    }

    protected function tearDown()
    {
        $this->globalContext = null;
        $this->context = null;
    }

    public function testInit()
    {
        $this->assertCount(0, $this->context->getViolations());
        $this->assertSame('Root', $this->context->getRoot());
        $this->assertSame('foo.bar', $this->context->getPropertyPath());
        $this->assertSame('Group', $this->context->getGroup());
    }

    public function testClone()
    {
        $clone = clone $this->context;

        // Cloning the context keeps the reference to the original violation
        // list. This way we can efficiently duplicate context instances during
        // the validation run and only modify the properties that need to be
        // changed.
        $this->assertSame($this->context->getViolations(), $clone->getViolations());
    }

    public function testAddViolation()
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Error', array('foo' => 'bar'))
            ->will($this->returnValue('Translated error'));

        $this->context->addViolation('Error', array('foo' => 'bar'), 'invalid');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Translated error',
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar',
                'invalid'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationUsesPreconfiguredValueIfNotPassed()
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Error', array())
            ->will($this->returnValue('Translated error'));

        $this->context->addViolation('Error');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Translated error',
                'Error',
                array(),
                'Root',
                'foo.bar',
                'currentValue'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationUsesPassedNullValue()
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Error', array('foo1' => 'bar1'))
            ->will($this->returnValue('Translated error'));
        $this->translator->expects($this->once())
            ->method('transChoice')
            ->with('Choice error', 1, array('foo2' => 'bar2'))
            ->will($this->returnValue('Translated choice error'));

        // passed null value should override preconfigured value "invalid"
        $this->context->addViolation('Error', array('foo1' => 'bar1'), null);
        $this->context->addViolation('Choice error', array('foo2' => 'bar2'), null, 1);

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Translated error',
                'Error',
                array('foo1' => 'bar1'),
                'Root',
                'foo.bar',
                null
            ),
            new ConstraintViolation(
                'Translated choice error',
                'Choice error',
                array('foo2' => 'bar2'),
                'Root',
                'foo.bar',
                null,
                1
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAt()
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Error', array('foo' => 'bar'))
            ->will($this->returnValue('Translated error'));

        // override preconfigured property path
        $this->context->addViolationAt('bam.baz', 'Error', array('foo' => 'bar'), 'invalid');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Translated error',
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar.bam.baz',
                'invalid'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtUsesPreconfiguredValueIfNotPassed()
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Error', array())
            ->will($this->returnValue('Translated error'));

        $this->context->addViolationAt('bam.baz', 'Error');

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Translated error',
                'Error',
                array(),
                'Root',
                'foo.bar.bam.baz',
                'currentValue'
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationAtUsesPassedNullValue()
    {
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Error', array('foo' => 'bar'))
            ->will($this->returnValue('Translated error'));
        $this->translator->expects($this->once())
            ->method('transChoice')
            ->with('Choice error', 2, array('foo' => 'bar'))
            ->will($this->returnValue('Translated choice error'));

        // passed null value should override preconfigured value "invalid"
        $this->context->addViolationAt('bam.baz', 'Error', array('foo' => 'bar'), null);
        $this->context->addViolationAt('bam.baz', 'Choice error', array('foo' => 'bar'), null, 2);

        $this->assertEquals(new ConstraintViolationList(array(
            new ConstraintViolation(
                'Translated error',
                'Error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar.bam.baz',
                null
            ),
            new ConstraintViolation(
                'Translated choice error',
                'Choice error',
                array('foo' => 'bar'),
                'Root',
                'foo.bar.bam.baz',
                null,
                2
            ),
        )), $this->context->getViolations());
    }

    public function testAddViolationPluralTranslationError()
    {
        $this->translator->expects($this->once())
            ->method('transChoice')
            ->with('foo')
            ->will($this->throwException(new \InvalidArgumentException()));
        $this->translator->expects($this->once())
            ->method('trans')
            ->with('foo');

        $this->context->addViolation('foo', array(), null, 2);
    }

    public function testGetPropertyPath()
    {
        $this->assertEquals('foo.bar', $this->context->getPropertyPath());
    }

    public function testGetPropertyPathWithIndexPath()
    {
        $this->assertEquals('foo.bar[bam]', $this->context->getPropertyPath('[bam]'));
    }

    public function testGetPropertyPathWithEmptyPath()
    {
        $this->assertEquals('foo.bar', $this->context->getPropertyPath(''));
    }

    public function testGetPropertyPathWithEmptyCurrentPropertyPath()
    {
        $this->context = new ExecutionContext($this->globalContext, $this->translator, self::TRANS_DOMAIN, $this->metadata, 'currentValue', 'Group', '');

        $this->assertEquals('bam.baz', $this->context->getPropertyPath('bam.baz'));
    }

    public function testGetPropertyPathWithNestedCollectionsAndAllMixed()
    {
        $constraints = new Collection(array(
            'shelves' => new All(array('constraints' => array(
                new Collection(array(
                    'name' => new ConstraintA(),
                    'books' => new All(array('constraints' => array(
                        new ConstraintA(),
                    ))),
                )),
            ))),
            'name' => new ConstraintA(),
        ));
        $data = array(
            'shelves' => array(
                array(
                    'name' => 'Research',
                    'books' => array('foo', 'bar'),
                ),
                array(
                    'name' => 'VALID',
                    'books' => array('foozy', 'VALID', 'bazzy'),
                ),
            ),
            'name' => 'Library',
        );
        $expectedViolationPaths = array(
            '[shelves][0][name]',
            '[shelves][0][books][0]',
            '[shelves][0][books][1]',
            '[shelves][1][books][0]',
            '[shelves][1][books][2]',
            '[name]',
        );

        $visitor = new ValidationVisitor('Root', $this->metadataFactory, new ConstraintValidatorFactory(), $this->translator);
        $context = new ExecutionContext($visitor, $this->translator, self::TRANS_DOMAIN);
        $context->validateValue($data, $constraints);

        foreach ($context->getViolations() as $violation) {
            $violationPaths[] = $violation->getPropertyPath();
        }

        $this->assertEquals($expectedViolationPaths, $violationPaths);
    }
}

class ExecutionContextTest_TestClass
{
    public $myProperty;
}
