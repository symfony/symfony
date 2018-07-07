<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver\Tests;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\NestedOption;
use Symfony\Component\OptionsResolver\ResolveData;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OptionsResolverNestedTest extends TestCase
{
    /**
     * @var OptionsResolver
     */
    private $resolver;

    protected function setUp()
    {
        $this->resolver = new OptionsResolver();
    }

    ////////////////////////////////////////////////////////////////////////////
    // resolve()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist. Defined options are: "a", "z".
     */
    public function testResolveFailsIfNonExistingOption()
    {
        $this->resolver->setDefault('z', new NestedOption(array('1')));
        $this->resolver->setDefault('a', new NestedOption(array('2')));

        $this->resolver->resolve(array('foo' => 'bar'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The options "baz", "foo", "ping" do not exist. Defined options are: "a", "z".
     */
    public function testResolveFailsIfMultipleNonExistingOptions()
    {
        $this->resolver->setDefault('z', new NestedOption(array('1')));
        $this->resolver->setDefault('a', new NestedOption(array('2')));

        $this->resolver->resolve(array('ping' => 'pong', 'foo' => 'bar', 'baz' => 'bam'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The nested option "foo" does not exist. Defined options are: "".
     */
    public function testUnknowNested()
    {
        $this->resolver->getNested('foo');
    }

    public function testHasDefault()
    {
        $this->assertFalse($this->resolver->hasDefault(array('foo', 'bar')));
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => 42,
                ))
            )
        ;
        $this->assertTrue($this->resolver->hasDefault(array('foo', 'bar')));
    }

    public function testHasDefaultWithNullValue()
    {
        $this->assertFalse($this->resolver->hasDefault(array('foo', 'bar')));
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => null,
                ))
            )
        ;
        $this->assertTrue($this->resolver->hasDefault(array('foo', 'bar')));
    }

    ////////////////////////////////////////////////////////////////////////////
    // lazy setDefault()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetLazyClosure()
    {
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => function (Options $options) {
                        return 'lazy';
                    },
                ))
            )
        ;

        $this->assertEquals(array('foo' => array('bar' => 'lazy')), $this->resolver->resolve());
    }

    public function testClosureWithoutTypeHintNotInvoked()
    {
        $closure = function ($options) {
            Assert::fail('Should not be called');
        };

        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => $closure,
                ))
            )
        ;

        $this->assertSame(array('foo' => array('bar' => $closure)), $this->resolver->resolve());
    }

    public function testClosureWithoutParametersNotInvoked()
    {
        $closure = function () {
            Assert::fail('Should not be called');
        };

        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => $closure,
                ))
            )
        ;

        $this->assertSame(array('foo' => array('bar' => $closure)), $this->resolver->resolve());
    }

    public function testAccessPreviousDefaultValue()
    {
        // defined by superclass
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => 'baz',
                ))
            )
        ;

        // defined by subclass
        $this
            ->resolver
            ->getNested('foo')
            ->setDefault('bar', function (Options $options, $previousValue) {
                Assert::assertEquals('baz', $previousValue);

                return 'lazy';
            })
        ;

        $this->assertEquals(array('foo' => array('bar' => 'lazy')), $this->resolver->resolve());
    }

    public function testAccessPreviousLazyDefaultValue()
    {
        // defined by superclass
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => function (Options $options) {
                        return 'baz';
                    },
                ))
            )
        ;

        // defined by subclass
        $this
            ->resolver
            ->getNested('foo')
            ->setDefault('bar', function (Options $options, $previousValue) {
                Assert::assertEquals('baz', $previousValue);

                return 'lazy';
            })
        ;

        $this->assertEquals(array('foo' => array('bar' => 'lazy')), $this->resolver->resolve());
    }

    public function testPreviousValueIsNotEvaluatedIfNoSecondArgument()
    {
        // defined by superclass
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => function () {
                        Assert::fail('Should not be called');
                    },
                ))
            )
        ;

        // defined by subclass, no $previousValue argument defined!
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => function (Options $options) {
                        return 'lazy';
                    },
                ))
            )
        ;

        $this->assertEquals(array('foo' => array('bar' => 'lazy')), $this->resolver->resolve());
    }

    public function testOverwrittenLazyOptionNotEvaluated()
    {
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => function () {
                        Assert::fail('Should not be called');
                    },
                ))
            )
        ;

        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'bar' => 'baz',
                ))
            )
        ;

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testInvokeEachLazyOptionOnlyOnce()
    {
        $calls = 0;

        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'lazy1' => function (Options $options) use (&$calls) {
                        Assert::assertSame(1, ++$calls);

                        $options['lazy2'];
                    },
                ))
            )
        ;

        $this
            ->resolver
            ->getNested('foo')
            ->setDefault('lazy2', function (Options $options) use (&$calls) {
                Assert::assertSame(2, ++$calls);
            })
        ;

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    ////////////////////////////////////////////////////////////////////////////
    // setRequired()/isRequired()/getRequiredOptions()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetRequiredFromLazyOption()
    {
        $this
            ->resolver
            ->setDefault('foo',
                new NestedOption(array(
                    'lazy1' => function (Options $options) {
                        $options->setRequired('lazy');
                    },
                ))
            )
        ;

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testResolveFailsIfRequiredOptionMissing()
    {
        $this->resolver->setRequired(array(
            array('foo', 'bar'),
        ));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfRequiredOptionSet()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
            ->setRequired(array(
                array('foo', 'bar'),
            ))
        ;

        $this
            ->resolver
            ->getNested('foo')
            ->setDefault('bar', 'baz')
        ;

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfRequiredOptionPassed()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
            ->setRequired(array(
                array('foo', 'bar'),
            ))
        ;

        $this->assertNotEmpty($this->resolver->resolve(array('foo' => array('bar' => 'any'))));
    }

    public function testIsRequired()
    {
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
            ->setRequired(array(
                array('foo', 'bar'),
            ))
        ;
        $this->assertTrue($this->resolver->isRequired(array('foo', 'bar')));
    }

    public function testRequiredIfSetBefore()
    {
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));
        $this
            ->resolver
            ->setDefault('foo', new NestedOption(array(
                'bar' => 'baz',
            )))
        ;
        $this
            ->resolver
            ->setRequired(array(
                array('foo', 'bar'),
            ))
        ;

        $this->assertTrue($this->resolver->isRequired(array('foo', 'bar')));
    }

    public function testStillRequiredAfterSet()
    {
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));

        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
            ->setRequired(array(
                array('foo', 'bar'),
            ))
        ;
        $this
            ->resolver
            ->getNested('foo')
            ->setDefault('bar', 'baz')
        ;

        $this->assertTrue($this->resolver->isRequired(array('foo', 'bar')));
    }

    public function testIsNotRequiredAfterRemove()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));
        $this->resolver->setRequired(array('foo', 'bar'));
        $this->resolver->remove(array('foo', 'bar'));
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));
    }

    public function testIsNotRequiredAfterClear()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));
        $this->resolver->getNested('foo')->setRequired('bar');
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));
    }

    public function testGetRequiredOptions()
    {
        $this
            ->resolver
            ->setDefault('any', new NestedOption())
        ;
        $this->resolver->setRequired(array(
            array('any', 'foo'),
            array('any', 'bar'),
        ));
        $this->resolver->getNested('any')->setDefault('bam', 'baz');
        $this->resolver->getNested('any')->setDefault('foo', 'boo');

        $this->assertEquals(array(array('any', 'foo'), array('any', 'bar')), $this->resolver->getRequiredOptions());
    }

    ////////////////////////////////////////////////////////////////////////////
    // isMissing()/getMissingOptions()
    ////////////////////////////////////////////////////////////////////////////

    public function testIsMissingIfNotSet()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isMissing(array('foo', 'bar')));
        $this->resolver->setRequired(array(
            array('foo', 'bar'),
        ));
        $this->assertTrue($this->resolver->isMissing(array('foo', 'bar')));
    }

    public function testIsNotMissingIfSet()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption(array(
                'bar' => 'baz',
            )))
        ;

        $this->assertFalse($this->resolver->isMissing(array('foo', 'bar')));
        $this->resolver->setRequired(array(
            array('foo', 'bar'),
        ));
        $this->assertFalse($this->resolver->isMissing(array('foo', 'bar')));
    }

    public function testIsNotMissingAfterRemove()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
            ->setRequired(array('foo', 'bar'))
        ;
        $this->resolver->remove(array('foo', 'bar'));

        $this->assertFalse($this->resolver->isMissing(array('foo', 'bar')));
    }

    public function testIsNotMissingAfterClear()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setRequired(array('foo', 'bar'));
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired(array('foo', 'bar')));
    }

    public function testGetMissingOptions()
    {
        $this
            ->resolver
            ->setDefault('any', new NestedOption())
        ;
        $this->resolver->setRequired(array(
            array('any', 'bar'),
        ));
        $this->resolver->getNested('any')->setDefault('bam', 'baz');
        $this->resolver->getNested('any')->setDefault('foo', 'boo');

        $this->assertEquals(array(array('any', 'bar')), $this->resolver->getMissingOptions());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setDefined()/isDefined()/getDefinedOptions()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefinedFromLazyOption()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption(array(
                'bar' => function (Options $options) {
                    $options->setDefined('baz');
                },
            )))
        ;

        $this->resolver->resolve();
    }

    public function testDefinedOptionsIncludedIfDefaultSetBefore()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this
            ->resolver
            ->getNested('foo')
            ->setDefault('bar', 'baz')
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetAfter()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this
            ->resolver
            ->getNested('foo')
            ->setDefault('bar', 'baz')
        ;

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfPassedToResolve()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));

        $this->assertSame(
            array('foo' => array('bar' => 'baz')),
            $this->resolver->resolve(array('foo' => array('bar' => 'baz')))
        );
    }

    public function testIsDefined()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->assertTrue($this->resolver->isDefined(array('foo', 'bar')));
    }

    public function testLazyOptionsAreDefined()
    {
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
            },
        )));
        $this->assertTrue($this->resolver->isDefined(array('foo', 'bar')));
    }

    public function testRequiredOptionsAreDefined()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->setRequired(array(
            array('foo', 'bar'),
        ));
        $this->assertTrue($this->resolver->isDefined(array('foo', 'bar')));
    }

    public function testSetOptionsAreDefined()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->assertTrue($this->resolver->isDefined(array('foo', 'bar')));
    }

    public function testGetDefinedOptions()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->resolver->setDefault('baz', 'bam');
        $this->resolver->setRequired(array(
            array('foo', 'boo'),
        ));

        $this->assertEquals(array(array('foo', 'bar'), array('foo', 'boo'), 'foo', 'baz'), $this->resolver->getDefinedOptions());
    }

    public function testRemovedOptionsAreNotDefined()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->assertTrue($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
    }

    public function testClearedOptionsAreNotDefined()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->assertTrue($this->resolver->isDefined(array('foo', 'bar')));
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isDefined(array('foo', 'bar')));
    }

    ////////////////////////////////////////////////////////////////////////////
    // setAllowedTypes()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetAllowedTypesFailsIfUnknownOption()
    {
        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'string');
    }

    public function testResolveTypedArray()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'string[]');
        $options = $this->resolver->resolve(array('foo' => array('bar' => array('baz', 'bam'))));

        $this->assertSame(array('foo' => array('bar' => array('baz', 'bam'))), $options);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetAllowedTypesFromLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => function (Options $options) {
                $options->setAllowedTypes('bar', 'string');
            },
        )));

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:bar" with value array is expected to be of type "int[]", but is of type
     *     "DateTime[]".
     */
    public function testResolveFailsIfInvalidTypedArray()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'int[]');

        $this->resolver->resolve(array('foo' => array('bar' => array(new \DateTime()))));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:bar" with value "baz" is expected to be of type "int[]", but is of type
     *     "string".
     */
    public function testResolveFailsWithNonArray()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'int[]');

        $this->resolver->resolve(array('foo' => array('bar' => 'baz')));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:bar" with value array is expected to be of type "int[]", but is of type
     *     "integer|stdClass|array|DateTime[]".
     */
    public function testResolveFailsIfTypedArrayContainsInvalidTypes()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'int[]');
        $values = range(1, 5);
        $values[] = new \stdClass();
        $values[] = array();
        $values[] = new \DateTime();
        $values[] = 123;

        $this->resolver->resolve(array('foo' => array('bar' => $values)));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:bar" with value array is expected to be of type "int[][]", but is of type
     *     "double[][]".
     */
    public function testResolveFailsWithCorrectLevelsButWrongScalar()
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'int[][]');

        $this->resolver->resolve(
            array(
                'foo' => array(
                    'bar' => array(
                        array(1.2),
                    ),
                ),
            )
        );
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testResolveFailsIfInvalidType($actualType, $allowedType, $exceptionMessage)
    {
        $this
            ->resolver
            ->setDefault('foo', new NestedOption())
        ;
        $this->resolver->setDefined(array(
            array('foo', 'option'),
        ));
        $this->resolver->setAllowedTypes(array('foo', 'option'), $allowedType);

        if (method_exists($this, 'expectException')) {
            $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
            $this->expectExceptionMessage($exceptionMessage);
        } else {
            $this->setExpectedException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException', $exceptionMessage);
        }

        $this->resolver->resolve(array('foo' => array('option' => $actualType)));
    }

    public function provideInvalidTypes()
    {
        return array(
            array(true, 'string', 'The option "foo:option" with value true is expected to be of type "string", but is of type "boolean".'),
            array(false, 'string', 'The option "foo:option" with value false is expected to be of type "string", but is of type "boolean".'),
            array(fopen(__FILE__, 'r'), 'string', 'The option "foo:option" with value resource is expected to be of type "string", but is of type "resource".'),
            array(array(), 'string', 'The option "foo:option" with value array is expected to be of type "string", but is of type "array".'),
            array(new OptionsResolver(), 'string', 'The option "foo:option" with value Symfony\Component\OptionsResolver\OptionsResolver is expected to be of type "string", but is of type "Symfony\Component\OptionsResolver\OptionsResolver".'),
            array(42, 'string', 'The option "foo:option" with value 42 is expected to be of type "string", but is of type "integer".'),
            array(null, 'string', 'The option "foo:option" with value null is expected to be of type "string", but is of type "NULL".'),
            array('bar', '\stdClass', 'The option "foo:option" with value "bar" is expected to be of type "\stdClass", but is of type "string".'),
        );
    }

    public function testResolveSucceedsIfValidType()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'bar',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:baz" with value 42 is expected to be of type "string" or "bool", but is of
     *     type "integer".
     */
    public function testResolveFailsIfInvalidTypeMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 42,
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), array('string', 'bool'));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidTypeMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => true,
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), array('string', 'bool'));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfInstanceOfClass()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => new \stdClass(),
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), '\stdClass');

        $this->resolver->resolve();
        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfNotInstanceOfClass()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'bar',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), '\stdClass');

        $this->resolver->resolve();
    }

    ////////////////////////////////////////////////////////////////////////////
    // addAllowedTypes()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testAddAllowedTypesFailsIfUnknownOption()
    {
        $this->resolver->setDefault('foo', new NestedOption());
        $this->resolver->addAllowedTypes(array('foo', 'baz'), 'string');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfAddAllowedTypesFromLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => function (Options $options) {
                $options->addAllowedTypes('bar', 'string');
            },
        )));

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedType()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 42,
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), 'string');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedType()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'bar',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedTypeMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 42,
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), array('string', 'bool'));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedTypeMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'baz',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), array('string', 'bool'));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'baz',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), 'bool');
        $this->resolver->addAllowedTypes(array('foo', 'baz'), 'string');

        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'baz',
        )));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite2()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'baz',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), 'bool');
        $this->resolver->addAllowedTypes(array('foo', 'baz'), 'string');

        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => false,
        )));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:baz" with value 23 is expected to be of type "bool" or "string", but is of type "integer"
     */
    public function testExceptionAddAllowedTypesDoesNotOverwrite()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'baz',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), 'bool');
        $this->resolver->addAllowedTypes(array('foo', 'baz'), 'string');

        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 23,
        )));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:baz" with value false is expected to be of type "array" or "string", but is of type "boolean"
     */
    public function testExceptionAddAllowedTypesDoesNotOverwrite2()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => 'baz',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'baz'), 'array');
        $this->resolver->addAllowedTypes(array('foo', 'baz'), 'string');

        $this->resolver->setDefault('foo', new NestedOption(array(
            'baz' => false,
        )));

        $this->assertNotEmpty($this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setAllowedValues()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetAllowedValuesFailsIfUnknownOption()
    {
        $this->resolver->setDefault('foo', new NestedOption());
        $this->resolver->setAllowedValues(array('foo', 'baz'), 'bar');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:bar" with value 42 is invalid. Accepted values are: "bar".
     */
    public function testResolveFailsIfInvalidValue()
    {
        $this->resolver->setDefault('foo', new NestedOption());
        $this->resolver->setDefined(array(
            array('foo', 'bar'),
        ));
        $this->resolver->setAllowedValues(array('foo', 'bar'), 'bar');

        $this->resolver->resolve(array('foo' => array('bar' => 42)));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:bar" with value null is invalid. Accepted values are: "bar".
     */
    public function testResolveFailsIfInvalidValueIsNull()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => null,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), 'bar');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidValueStrict()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => '42',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), 42);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValue()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), 'baz');

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidValueIsNull()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => null,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), null);

        $this->assertEquals(array('foo' => array('bar' => null)), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo:bar" with value 42 is invalid. Accepted values are: "bar", false, null.
     */
    public function testResolveFailsIfInvalidValueMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), array('bar', false, null));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValueMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), array('baz', 'bar'));

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testResolveFailsIfClosureReturnsFalse()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), function ($value) use (&$passedValue) {
            $passedValue = $value;

            return false;
        });

        try {
            $this->resolver->resolve();
            $this->fail('Should fail');
        } catch (InvalidOptionsException $e) {
        }

        $this->assertSame(42, $passedValue);
    }

    public function testResolveSucceedsIfClosureReturnsTrue()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), function ($value) use (&$passedValue) {
            $passedValue = $value;

            return true;
        });

        $this->assertEquals(array('foo' => array('bar' => 42)), $this->resolver->resolve());
        $this->assertSame(42, $passedValue);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfAllClosuresReturnFalse()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), array(
            function () {
                return false;
            },
            function () {
                return false;
            },
            function () {
                return false;
            },
        ));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyClosureReturnsTrue()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), array(
            function () {
                return false;
            },
            function () {
                return true;
            },
            function () {
                return false;
            },
        ));

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // addAllowedValues()
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testAddAllowedValuesFailsIfUnknownOption()
    {
        $this->resolver->setDefault('foo', new NestedOption());
        $this->resolver->addAllowedValues(array('foo', 'baz'), 'bar');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfAddAllowedValuesFromLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
                $options->addAllowedValues('bar', 'baz');
            },
        )));

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedValue()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->addAllowedValues(array('foo', 'bar'), 'baz');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValue()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->addAllowedValues(array('foo', 'bar'), 'baz');

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidAddedValueIsNull()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => null,
        )));
        $this->resolver->addAllowedValues(array('foo', 'bar'), null);

        $this->assertEquals(array('foo' => array('bar' => null)), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedValueMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->addAllowedValues(array('foo', 'bar'), array('baz', 'bar'));

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValueMultiple()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->addAllowedValues(array('foo', 'bar'), array('baz', 'bam'));

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->addAllowedValues(array('foo', 'bar'), 'bam');
        $this->resolver->addAllowedValues(array('foo', 'bar'), 'baz');

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite2()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), 'bam');
        $this->resolver->addAllowedValues(array('foo', 'bar'), 'baz');

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfAllAddedClosuresReturnFalse()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), function () {
            return false;
        });
        $this->resolver->addAllowedValues(array('foo', 'bar'), function () {
            return false;
        });

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 42,
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), function () {
            return false;
        });
        $this->resolver->addAllowedValues(array('foo', 'bar'), function () {
            return true;
        });

        $this->assertEquals(array('foo' => array('bar' => 42)), $this->resolver->resolve());
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue2()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), function () {
            return true;
        });
        $this->resolver->addAllowedValues(array('foo', 'bar'), function () {
            return false;
        });

        $this->assertEquals(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setNormalizer()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetNormalizerReturnsThis()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));

        $this->assertSame($this->resolver, $this->resolver->setNormalizer(array('foo', 'bar'), function () {
        }));
    }

    public function testSetNormalizerClosure()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setNormalizer(array('foo', 'bar'), function () {
            return 'normalized';
        });

        $this->assertEquals(array('foo' => array('bar' => 'normalized')), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetNormalizerFailsIfUnknownOption()
    {
        $this->resolver->setDefault('foo', new NestedOption());
        $this->resolver->setNormalizer(array('foo', 'bar'), function () {
        });
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetNormalizerFromLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
                $options->setNormalizer('baz', function () {
                });
            },
        )));

        $this->resolver->resolve();
    }

    public function testNormalizerReceivesSetOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setNormalizer(array('foo', 'bar'), function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $this->assertEquals(array('foo' => array('bar' => 'normalized[baz]')), $this->resolver->resolve());
    }

    public function testNormalizerReceivesPassedOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setNormalizer(array('foo', 'bar'), function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $resolved = $this->resolver->resolve(array('foo' => array('bar' => 'bam')));

        $this->assertEquals(array('foo' => array('bar' => 'normalized[bam]')), $resolved);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateTypeBeforeNormalization()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));

        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'int');

        $this->resolver->setNormalizer(array('foo', 'bar'), function () {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateValueBeforeNormalization()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));

        $this->resolver->setAllowedValues(array('foo', 'bar'), 'bam');

        $this->resolver->setNormalizer(array('foo', 'bar'), function () {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    public function testNormalizerCanAccessOtherOptions()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default' => 'bar',
            'norm' => 'bar',
        )));

        $this->resolver->setNormalizer(array('foo', 'norm'), function (Options $options) {
            /* @var TestCase $test */
            Assert::assertSame('bar', $options['default']);

            return 'normalized';
        });

        $this->assertEquals(array(
            'foo' => array(
                'norm' => 'normalized',
                'default' => 'bar',
            ),
        ), $this->resolver->resolve());
    }

    public function testNormalizerCanAccessLazyOptions()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'lazy' => function (Options $options) {
                return 'bar';
            },
        )));
        $this->resolver->getNested('foo')->setDefault('norm', 'baz');

        $this->resolver->setNormalizer(array('foo', 'norm'), function (Options $options) {
            /* @var TestCase $test */
            Assert::assertEquals('bar', $options['lazy']);

            return 'normalized';
        });

        $this->assertEquals(array(
            'foo' => array(
                'lazy' => 'bar',
                'norm' => 'normalized',
            ),
        ), $this->resolver->resolve());
    }

    /**
     * @expectedExceptionMessage The options "foo:norm1", "foo:norm2" have a cyclic dependency.
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependencyBetweenNormalizers()
    {
        $this->resolver->setDefault('foo', new NestedOption());
        $this->resolver->getNested('foo')->setDefault('norm1', 'bar');
        $this->resolver->getNested('foo')->setDefault('norm2', 'baz');

        $this->resolver->setNormalizer(array('foo', 'norm1'), function (Options $options) {
            $options['norm2'];
        });

        $this->resolver->setNormalizer(array('foo', 'norm2'), function (Options $options) {
            $options['norm1'];
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedExceptionMessage The options "foo:lazy", "foo:norm" have a cyclic dependency.
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependencyBetweenNormalizerAndLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'lazy' => function (Options $options) {
                $options['norm'];
            },
        )));

        $this->resolver->setDefault('foo', new NestedOption(array(
            'norm' => function (Options $options) {
                $options['norm'];
            },
        )));

        $this->resolver->setNormalizer(array('foo', 'norm'), function (Options $options) {
            $options['lazy'];
        });

        $this->resolver->resolve();
    }

    public function testCaughtExceptionFromNormalizerDoesNotCrashOptionResolver()
    {
        $throw = true;

        $this->resolver->setDefault('foo', new NestedOption(array(
            'catcher' => null,
            'thrower' => null,
        )));

        $this->resolver->setNormalizer(array('foo', 'catcher'), function (Options $options) {
            try {
                return $options['thrower'];
            } catch (\Exception $e) {
                return false;
            }
        });

        $this->resolver->setNormalizer(array('foo', 'thrower'), function () use (&$throw) {
            if ($throw) {
                $throw = false;
                throw new \UnexpectedValueException('throwing');
            }

            return true;
        });

        $this->assertEquals(array('foo' => array('catcher' => false, 'thrower' => true)), $this->resolver->resolve());
    }

    public function testCaughtExceptionFromLazyDoesNotCrashOptionResolver()
    {
        $throw = true;

        $this->resolver->setDefault('foo', new NestedOption(array(
            'catcher' => function (Options $options) {
                try {
                    return $options['thrower'];
                } catch (\Exception $e) {
                    return false;
                }
            },
        )));

        $this->resolver->setDefault('foo', new NestedOption(array(
            'thrower' => function (Options $options) use (&$throw) {
                if ($throw) {
                    $throw = false;
                    throw new \UnexpectedValueException('throwing');
                }

                return true;
            },
        )));

        $this->assertEquals(array('foo' => array('catcher' => false, 'thrower' => true)), $this->resolver->resolve());
    }

    public function testInvokeEachNormalizerOnlyOnce()
    {
        $calls = 0;

        $this->resolver->setDefault('foo', new NestedOption(array(
            'norm1' => 'bar',
            'norm2' => 'baz',
        )));

        $this->resolver->setNormalizer(array('foo', 'norm1'), function ($options) use (&$calls) {
            Assert::assertSame(1, ++$calls);

            $options['norm2'];
        });
        $this->resolver->setNormalizer(array('foo', 'norm2'), function () use (&$calls) {
            Assert::assertSame(2, ++$calls);
        });

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    public function testNormalizerNotCalledForUnsetOptions()
    {
        $this->resolver->setDefault('foo', new NestedOption());

        $this->resolver->setDefined(array(
            array('foo', 'norm'),
        ));

        $this->resolver->setNormalizer(array('foo', 'norm'), function () {
            Assert::fail('Should not be called.');
        });

        $this->assertEquals(array('foo' => array()), $this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // setDefaults()
    ////////////////////////////////////////////////////////////////////////////

    public function testSetDefaultsReturnsThis()
    {
        $this->assertSame($this->resolver, $this->resolver->setDefaults(array('foo', new NestedOption(array(
            'bar' => 'baz',
        )))));
    }

    public function testSetDefaults()
    {
        $this->resolver->setDefaults(array('foo' => new NestedOption(array(
            'one' => '1',
        ))));
        $this->resolver->getNested('foo')->setDefault('two', 'bar');

        $this->resolver->setDefaults(array(
            'foo' => new NestedOption(array(
                'two' => '2',
                'three' => '3',
            )),
        ));

        $this->assertEquals(array(
            'foo' => array(
                'one' => '1',
                'two' => '2',
                'three' => '3',
            ),
        ), $this->resolver->resolve());
    }

    /**
     * @expectedExceptionMessage Default values cannot be set from a lazy option or normalizer.
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefaultsFromLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
                $options->setDefaults(array('two' => '2'));
            },
        )));

        $this->resolver->resolve();
    }

    ////////////////////////////////////////////////////////////////////////////
    // remove()
    ////////////////////////////////////////////////////////////////////////////

    public function testRemoveReturnsThis()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));

        $this->assertSame($this->resolver, $this->resolver->remove(array('foo', 'bar')));
        $this->assertSame($this->resolver, $this->resolver->remove('foo'));
    }

    public function testRemoveSingleOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
            'bom' => 'bam',
        )));
        $this->resolver->remove(array(
            array('foo', 'bar'),
        ));

        $this->assertSame(array('foo' => array('bom' => 'bam')), $this->resolver->resolve());
    }

    public function testRemoveMultipleOptions()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
            'bom' => 'bam',
        )));
        $this->resolver->getNested('foo')->setDefault('doo', 'dam');

        $this->resolver->remove(array(
            array('foo', 'doo'),
            array('foo', 'bar'),
        ));

        $this->assertSame(array('foo' => array('bom' => 'bam')), $this->resolver->resolve());
    }

    public function testRemoveLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
                return 'lazy';
            },
        )));
        $this->resolver->remove(array(
            array('foo', 'bar'),
        ));

        $this->assertSame(array('foo' => array()), $this->resolver->resolve());
    }

    public function testRemoveNormalizer()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));

        $this->resolver->setNormalizer(array('foo', 'bar'), function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->remove(array(
            array('foo', 'bar'),
        ));
        $this->resolver->getNested('foo')->setDefault('bar', 'baz');

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testRemoveAllowedTypes()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));

        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'int');
        $this->resolver->remove(array(
            array('foo', 'bar'),
        ));
        $this->resolver->getNested('foo')->setDefault('bar', 'baz');

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testRemoveAllowedValues()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), array('baz', 'boo'));
        $this->resolver->remove(array(
            array('foo', 'bar'),
        ));
        $this->resolver->getNested('foo')->setDefault('bar', 'baz');

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    /**
     * @expectedExceptionMessage Options cannot be removed from a lazy option or normalizer.
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfRemoveFromLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
                $options->remove('bar');
            },
        )));

        $this->resolver->resolve();
    }

    public function testRemoveUnknownOptionIgnored()
    {
        $this->assertNotNull($this->resolver->remove(array(
            array('foo', 'bar'),
        )));
    }

    ////////////////////////////////////////////////////////////////////////////
    // clear()
    ////////////////////////////////////////////////////////////////////////////

    public function testClearRemovesAllOptions()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
            'bom' => 'bam',
        )));

        $this->resolver->clear();

        $this->assertEmpty($this->resolver->resolve());
    }

    public function testClearRemovesAllOptionsInNested()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
            'bom' => 'bam',
        )));

        $this->resolver->clear('foo');
        $this->assertSame(array('foo' => array()), $this->resolver->resolve());

        $this->resolver->clear();
        $this->assertEmpty($this->resolver->resolve());
    }

    public function testClearLazyOption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
                return 'lazy';
            },
        )));
        $this->resolver->clear('foo');
        $this->assertSame(array('foo' => array()), $this->resolver->resolve());

        $this->resolver->clear();
        $this->assertEmpty($this->resolver->resolve());
    }

    public function testClearNormalizer()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setNormalizer(array('foo', 'bar'), function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->clear('foo');
        $this->resolver->getNested('foo')->setDefault('bar', 'baz');

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testClearAllowedTypes()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedTypes(array('foo', 'bar'), 'integer');
        $this->resolver->clear('foo');
        $this->resolver->getNested('foo')->setDefault('bar', 'baz');

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    public function testClearAllowedValues()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setAllowedValues(array('foo', 'bar'), 'integer');
        $this->resolver->clear('foo');
        $this->resolver->getNested('foo')->setDefault('bar', 'baz');

        $this->assertSame(array('foo' => array('bar' => 'baz')), $this->resolver->resolve());
    }

    /**
     * @expectedExceptionMessage Options cannot be cleared from a lazy option or normalizer.
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfClearFromLazyption()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => function (Options $options) {
                $options->clear();
            },
        )));

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testClearOptionAndNormalizer()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => 'baz',
        )));
        $this->resolver->setNormalizer(array('foo', 'bar'), function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bam' => 'baz',
        )));
        $this->resolver->setNormalizer(array('foo', 'bam'), function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->clear();

        $this->assertEmpty($this->resolver->resolve());
    }

    public function testNestedClear()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'bar' => new NestedOption(array(
                'bam' => new NestedOption(array(
                    'bom' => 'dam',
                )),
            )),
        )));
        $this->assertSame(array('foo' => array('bar' => array('bam' => array('bom' => 'dam')))), $this->resolver->resolve());

        $this->resolver->clear(array('foo', 'bar', 'bam'));
        $this->assertSame(array('foo' => array('bar' => array('bam' => array()))), $this->resolver->resolve());

        $this->resolver->clear(array('foo', 'bar'));
        $this->assertSame(array('foo' => array('bar' => array())), $this->resolver->resolve());

        $this->resolver->clear(array('foo'));
        $this->assertSame(array('foo' => array()), $this->resolver->resolve());
    }

    ////////////////////////////////////////////////////////////////////////////
    // ArrayAccess
    ////////////////////////////////////////////////////////////////////////////

    public function testArrayAccess()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => 0,
            'default2' => 1,
        )));
        $this->resolver->setRequired(array(
            array('foo', 'required'),
        ));
        $this->resolver->setDefined(array(
            array('foo', 'defined'),
        ));
        $this->resolver->setDefault('foo', new NestedOption(array(
            'lazy1' => function (Options $options) {
                return 'lazy';
            },
        )));
        $this->resolver->setDefault('foo', new NestedOption(array(
            'lazy2' => function (Options $options) {
                Assert::assertArrayHasKey('default1', $options);
                Assert::assertArrayHasKey('default1', $options);
                Assert::assertArrayHasKey('default2', $options);
                Assert::assertArrayHasKey('required', $options);
                Assert::assertArrayHasKey('lazy1', $options);
                Assert::assertArrayHasKey('lazy2', $options);
                Assert::assertArrayNotHasKey('defined', $options);

                Assert::assertSame(0, $options['default1']);
                Assert::assertSame(42, $options['default2']);
                Assert::assertSame('value', $options['required']);
                Assert::assertSame('lazy', $options['lazy1']);
            },
        )));

        $this->resolver->resolve(array('foo' => array('default2' => 42, 'required' => 'value')));
    }

    ////////////////////////////////////////////////////////////////////////////
    // Other tests for get 100% coverage
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The nested option "foo" does not exist. Defined options are: "".
     */
    public function testSetDefineThrowException()
    {
        $this->resolver->setDefined(array(
            array('foo', 'bar', 'bam'),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The nested option "foo" does not exist. Defined options are: "".
     */
    public function testNormalizerThrowException()
    {
        $this->resolver->setNormalizer(array('foo', 'bar', 'bam'), function () {
            return null;
        });
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The nested option "foo" does not exist. Defined options are: "".
     */
    public function testSetAllowValuesThrowException()
    {
        $this->resolver->setAllowedValues(array('foo', 'bar', 'bam'), array(null));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The nested option "foo" does not exist. Defined options are: "".
     */
    public function testAddAllowValuesThrowException()
    {
        $this->resolver->addAllowedValues(array('foo', 'bar', 'bam'), array(null));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The nested option "foo" does not exist. Defined options are: "".
     */
    public function testAddAllowTypesThrowException()
    {
        $this->resolver->addAllowedTypes(array('foo', 'bar', 'bam'), array(null));
    }

    public function testClearReturnFalse()
    {
        $this->assertSame($this->resolver, $this->resolver->clear(array('foo', 'bar', 'bam')));
    }

    public function testOffsetGetNested()
    {
        $this->resolver->setDefaults(array(
            'host' => function (Options $options) {
                return $options['db']['host'];
            },
            'db' => new NestedOption(array(
                'host' => 'localhost',
            )),
        ));
        $this->assertEquals(array('host' => 'localhost', 'db' => array('host' => 'localhost')), $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "string[][]",
     * but is of type "string[]".
     */
    public function testSkipInFormatTypeOf()
    {
        $this->resolver->setDefault('foo', array(
            'bar',
            'baz',
        ));
        $this->resolver->setAllowedTypes('foo', 'string[][]');
        $this->resolver->resolve();
    }

    public function testSetRequiredArray()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => 0,
            'default2' => 1,
        )));

        $this->resolver->setRequired(array(array('foo')));
        $this->resolver->setDefined(array(array('foo')));
        $this->assertEquals(array('foo' => array('default1' => 0, 'default2' => 1)), $this->resolver->resolve());

        $this->resolver->remove(array(array('foo')));
        $this->assertSame(array(), $this->resolver->resolve());
    }

    public function testChildrenAccess()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => 0,
            'default2' => 1,
        )));
        $this->resolver->setDefault('data', function (Options $option) {
            return $option['foo']['default1'];
        });
        $this->assertEquals(array('foo' => array('default1' => 0, 'default2' => 1), 'data' => 0), $this->resolver->resolve());

        $this->assertEquals(array('foo' => array('default1' => 12, 'default2' => 1), 'data' => 12), $this->resolver->resolve(array(
            'foo' => array(
                'default1' => 12,
            ),
        )));
    }

    public function testChildrenAccessNormalize()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => 0,
            'default2' => 1,
        )));
        $this->resolver->setDefault('data', function (Options $option) {
            return $option['foo']['default1'];
        });
        $this->resolver->setNormalizer(array('foo', 'default1'), function (Options $options, $value) {
            return 'test_'.$value;
        });

        $this->assertEquals(array('foo' => array('default1' => 'test_0', 'default2' => 1), 'data' => 'test_0'), $this->resolver->resolve());

        $this->assertEquals(array('foo' => array('default1' => 'test_12', 'default2' => 1), 'data' => 'test_12'), $this->resolver->resolve(array(
            'foo' => array(
                'default1' => 12,
            ),
        )));
    }

    public function testChildrenAccessNormalize2()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => 0,
            'default2' => 1,
        )));
        $this->resolver->setDefault('data', function (Options $option) {
            return $option['foo']['default1'];
        });

        $this->resolver->setNormalizer('data', function (Options $options, $value) {
            return 'test_'.$value;
        });
        $this->assertEquals(array('foo' => array('default1' => 0, 'default2' => 1), 'data' => 'test_0'), $this->resolver->resolve());

        $this->assertEquals(array('foo' => array('default1' => 12, 'default2' => 1), 'data' => 'test_12'), $this->resolver->resolve(array(
            'foo' => array(
                'default1' => 12,
            ),
        )));
    }

    public function testParentAccess()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => 1,
            'default2' => new NestedOption(array(
                'any' => function (ResolveData $option) {
                    return $option['foo']['default1'];
                },
            )),
        )));

        $this->assertEquals(array('foo' => array('default1' => 1, 'default2' => array('any' => 1))), $this->resolver->resolve());
        $this->assertEquals(array('foo' => array('default1' => 'text', 'default2' => array('any' => 'text'))), $this->resolver->resolve(array(
            'foo' => array(
                'default1' => 'text',
            ),
        )));
    }

    public function testParentAccessNormalize()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => 1,
            'default2' => new NestedOption(array(
                'any' => function (ResolveData $option) {
                    return $option['foo']['default1'];
                },
            )),
        )));

        $this->resolver->setNormalizer(array('foo', 'default1'), function (Options $options, $value) {
            return 'test_'.$value;
        });

        $this->assertEquals(array('foo' => array('default1' => 'test_1', 'default2' => array('any' => 'test_1'))), $this->resolver->resolve());
        $this->assertEquals(array('foo' => array('default1' => 'test_text', 'default2' => array('any' => 'test_text'))), $this->resolver->resolve(array(
            'foo' => array(
                'default1' => 'text',
            ),
        )));
    }

    public function testParentAndChildrenAccessNormalize()
    {
        $this->resolver->setDefault('foo', new NestedOption(array(
            'default1' => function (Options $options) {
                return $options['default2']['any'];
            },
            'default2' => new NestedOption(array(
                'bar' => 'baz',
                'any' => function (ResolveData $option) {
                    return $option['foo']['default2']['bar'];
                },
            )),
        )));

        $this->assertEquals(array('foo' => array('default1' => 'baz', 'default2' => array('any' => 'baz', 'bar' => 'baz'))), $this->resolver->resolve());
    }
}
