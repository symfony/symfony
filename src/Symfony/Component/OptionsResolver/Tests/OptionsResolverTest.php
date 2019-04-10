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
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OptionsResolverTest extends TestCase
{
    /**
     * @var OptionsResolver
     */
    private $resolver;

    protected function setUp()
    {
        $this->resolver = new OptionsResolver();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist. Defined options are: "a", "z".
     */
    public function testResolveFailsIfNonExistingOption()
    {
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(['foo' => 'bar']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The options "baz", "foo", "ping" do not exist. Defined options are: "a", "z".
     */
    public function testResolveFailsIfMultipleNonExistingOptions()
    {
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(['ping' => 'pong', 'foo' => 'bar', 'baz' => 'bam']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testResolveFailsFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->resolve([]);
        });

        $this->resolver->resolve();
    }

    public function testSetDefaultReturnsThis()
    {
        $this->assertSame($this->resolver, $this->resolver->setDefault('foo', 'bar'));
    }

    public function testSetDefault()
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', '20');

        $this->assertEquals([
            'one' => '1',
            'two' => '20',
        ], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefaultFromLazyOption()
    {
        $this->resolver->setDefault('lazy', function (Options $options) {
            $options->setDefault('default', 42);
        });

        $this->resolver->resolve();
    }

    public function testHasDefault()
    {
        $this->assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', 42);
        $this->assertTrue($this->resolver->hasDefault('foo'));
    }

    public function testHasDefaultWithNullValue()
    {
        $this->assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', null);
        $this->assertTrue($this->resolver->hasDefault('foo'));
    }

    public function testSetLazyReturnsThis()
    {
        $this->assertSame($this->resolver, $this->resolver->setDefault('foo', function (Options $options) {}));
    }

    public function testSetLazyClosure()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testClosureWithoutTypeHintNotInvoked()
    {
        $closure = function ($options) {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        $this->assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testClosureWithoutParametersNotInvoked()
    {
        $closure = function () {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        $this->assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testAccessPreviousDefaultValue()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', 'bar');

        // defined by subclass
        $this->resolver->setDefault('foo', function (Options $options, $previousValue) {
            Assert::assertEquals('bar', $previousValue);

            return 'lazy';
        });

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testAccessPreviousLazyDefaultValue()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'bar';
        });

        // defined by subclass
        $this->resolver->setDefault('foo', function (Options $options, $previousValue) {
            Assert::assertEquals('bar', $previousValue);

            return 'lazy';
        });

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testPreviousValueIsNotEvaluatedIfNoSecondArgument()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function () {
            Assert::fail('Should not be called');
        });

        // defined by subclass, no $previousValue argument defined!
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });

        $this->assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testOverwrittenLazyOptionNotEvaluated()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            Assert::fail('Should not be called');
        });

        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testInvokeEachLazyOptionOnlyOnce()
    {
        $calls = 0;

        $this->resolver->setDefault('lazy1', function (Options $options) use (&$calls) {
            Assert::assertSame(1, ++$calls);

            $options['lazy2'];
        });

        $this->resolver->setDefault('lazy2', function (Options $options) use (&$calls) {
            Assert::assertSame(2, ++$calls);
        });

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    public function testSetRequiredReturnsThis()
    {
        $this->assertSame($this->resolver, $this->resolver->setRequired('foo'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetRequiredFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setRequired('bar');
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     */
    public function testResolveFailsIfRequiredOptionMissing()
    {
        $this->resolver->setRequired('foo');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfRequiredOptionSet()
    {
        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfRequiredOptionPassed()
    {
        $this->resolver->setRequired('foo');

        $this->assertNotEmpty($this->resolver->resolve(['foo' => 'bar']));
    }

    public function testIsRequired()
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testRequiredIfSetBefore()
    {
        $this->assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setRequired('foo');

        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testStillRequiredAfterSet()
    {
        $this->assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertTrue($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterRemove()
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterClear()
    {
        $this->assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetRequiredOptions()
    {
        $this->resolver->setRequired(['foo', 'bar']);
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        $this->assertSame(['foo', 'bar'], $this->resolver->getRequiredOptions());
    }

    public function testIsMissingIfNotSet()
    {
        $this->assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingIfSet()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        $this->assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterRemove()
    {
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterClear()
    {
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetMissingOptions()
    {
        $this->resolver->setRequired(['foo', 'bar']);
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        $this->assertSame(['bar'], $this->resolver->getMissingOptions());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefinedFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setDefined('bar');
        });

        $this->resolver->resolve();
    }

    public function testDefinedOptionsNotIncludedInResolvedOptions()
    {
        $this->resolver->setDefined('foo');

        $this->assertSame([], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetBefore()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefined('foo');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetAfter()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfPassedToResolve()
    {
        $this->resolver->setDefined('foo');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve(['foo' => 'bar']));
    }

    public function testIsDefined()
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testLazyOptionsAreDefined()
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', function (Options $options) {});
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testRequiredOptionsAreDefined()
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setRequired('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testSetOptionsAreDefined()
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', 'bar');
        $this->assertTrue($this->resolver->isDefined('foo'));
    }

    public function testGetDefinedOptions()
    {
        $this->resolver->setDefined(['foo', 'bar']);
        $this->resolver->setDefault('baz', 'bam');
        $this->resolver->setRequired('boo');

        $this->assertSame(['foo', 'bar', 'baz', 'boo'], $this->resolver->getDefinedOptions());
    }

    public function testRemovedOptionsAreNotDefined()
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->remove('foo');
        $this->assertFalse($this->resolver->isDefined('foo'));
    }

    public function testClearedOptionsAreNotDefined()
    {
        $this->assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        $this->assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->clear();
        $this->assertFalse($this->resolver->isDefined('foo'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDeprecatedFromLazyOption()
    {
        $this->resolver
            ->setDefault('bar', 'baz')
            ->setDefault('foo', function (Options $options) {
                $options->setDeprecated('bar');
            })
            ->resolve()
        ;
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetDeprecatedFailsIfUnknownOption()
    {
        $this->resolver->setDeprecated('foo');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid type for deprecation message argument, expected string or \Closure, but got "boolean".
     */
    public function testSetDeprecatedFailsIfInvalidDeprecationMessageType()
    {
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo', true)
        ;
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid type for deprecation message, expected string but got "boolean", return an empty string to ignore.
     */
    public function testLazyDeprecationFailsIfInvalidDeprecationMessageType()
    {
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo', function (Options $options, $value) {
                return false;
            })
        ;
        $this->resolver->resolve(['foo' => null]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     * @expectedExceptionMessage The options "foo", "bar" have a cyclic dependency.
     */
    public function testFailsIfCyclicDependencyBetweenDeprecation()
    {
        $this->resolver
            ->setDefined(['foo', 'bar'])
            ->setDeprecated('foo', function (Options $options, $value) {
                $options['bar'];
            })
            ->setDeprecated('bar', function (Options $options, $value) {
                $options['foo'];
            })
        ;
        $this->resolver->resolve(['foo' => null, 'bar' => null]);
    }

    public function testIsDeprecated()
    {
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo')
        ;
        $this->assertTrue($this->resolver->isDeprecated('foo'));
    }

    public function testIsNotDeprecatedIfEmptyString()
    {
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo', '')
        ;
        $this->assertFalse($this->resolver->isDeprecated('foo'));
    }

    /**
     * @dataProvider provideDeprecationData
     */
    public function testDeprecationMessages(\Closure $configureOptions, array $options, ?array $expectedError, int $expectedCount)
    {
        $count = 0;
        error_clear_last();
        set_error_handler(function () use (&$count) {
            ++$count;

            return false;
        });
        $e = error_reporting(0);

        $configureOptions($this->resolver);
        $this->resolver->resolve($options);

        error_reporting($e);
        restore_error_handler();

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        $this->assertSame($expectedError, $lastError);
        $this->assertSame($expectedCount, $count);
    }

    public function provideDeprecationData()
    {
        yield 'It deprecates an option with default message' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined(['foo', 'bar'])
                    ->setDeprecated('foo')
                ;
            },
            ['foo' => 'baz'],
            [
                'type' => E_USER_DEPRECATED,
                'message' => 'The option "foo" is deprecated.',
            ],
            1,
        ];

        yield 'It deprecates an option with custom message' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined('foo')
                    ->setDefault('bar', function (Options $options) {
                        return $options['foo'];
                    })
                    ->setDeprecated('foo', 'The option "foo" is deprecated, use "bar" option instead.')
                ;
            },
            ['foo' => 'baz'],
            [
                'type' => E_USER_DEPRECATED,
                'message' => 'The option "foo" is deprecated, use "bar" option instead.',
            ],
            2,
        ];

        yield 'It deprecates an option evaluated in another definition' => [
            function (OptionsResolver $resolver) {
                // defined by superclass
                $resolver
                    ->setDefault('foo', null)
                    ->setDeprecated('foo')
                ;
                // defined by subclass
                $resolver->setDefault('bar', function (Options $options) {
                    return $options['foo']; // It triggers a deprecation
                });
            },
            [],
            [
                'type' => E_USER_DEPRECATED,
                'message' => 'The option "foo" is deprecated.',
            ],
            1,
        ];

        yield 'It deprecates allowed type and value' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefault('foo', null)
                    ->setAllowedTypes('foo', ['null', 'string', \stdClass::class])
                    ->setDeprecated('foo', function (Options $options, $value) {
                        if ($value instanceof \stdClass) {
                            return sprintf('Passing an instance of "%s" to option "foo" is deprecated, pass its FQCN instead.', \stdClass::class);
                        }

                        return '';
                    })
                ;
            },
            ['foo' => new \stdClass()],
            [
                'type' => E_USER_DEPRECATED,
                'message' => 'Passing an instance of "stdClass" to option "foo" is deprecated, pass its FQCN instead.',
            ],
            1,
        ];

        yield 'It triggers a deprecation based on the value only if option is provided by the user' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined('foo')
                    ->setAllowedTypes('foo', ['null', 'bool'])
                    ->setDeprecated('foo', function (Options $options, $value) {
                        if (!\is_bool($value)) {
                            return 'Passing a value different than true or false is deprecated.';
                        }

                        return '';
                    })
                    ->setDefault('baz', null)
                    ->setAllowedTypes('baz', ['null', 'int'])
                    ->setDeprecated('baz', function (Options $options, $value) {
                        if (!\is_int($value)) {
                            return 'Not passing an integer is deprecated.';
                        }

                        return '';
                    })
                    ->setDefault('bar', function (Options $options) {
                        $options['baz']; // It does not triggers a deprecation

                        return $options['foo']; // It does not triggers a deprecation
                    })
                ;
            },
            ['foo' => null], // It triggers a deprecation
            [
                'type' => E_USER_DEPRECATED,
                'message' => 'Passing a value different than true or false is deprecated.',
            ],
            1,
        ];

        yield 'It ignores a deprecation if closure returns an empty string' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefault('foo', null)
                    ->setDeprecated('foo', function (Options $options, $value) {
                        return '';
                    })
                ;
            },
            ['foo' => Bar::class],
            null,
            0,
        ];

        yield 'It deprecates value depending on other option value' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefault('widget', null)
                    ->setDefault('date_format', null)
                    ->setDeprecated('date_format', function (Options $options, $dateFormat) {
                        if (null !== $dateFormat && 'single_text' === $options['widget']) {
                            return 'Using the "date_format" option when the "widget" option is set to "single_text" is deprecated.';
                        }

                        return '';
                    })
                ;
            },
            ['widget' => 'single_text', 'date_format' => 2],
            [
                'type' => E_USER_DEPRECATED,
                'message' => 'Using the "date_format" option when the "widget" option is set to "single_text" is deprecated.',
            ],
            1,
        ];

        yield 'It triggers a deprecation for each evaluation' => [
            function (OptionsResolver $resolver) {
                $resolver
                    // defined by superclass
                    ->setDefined('foo')
                    ->setDeprecated('foo')
                    // defined by subclass
                    ->setDefault('bar', function (Options $options) {
                        return $options['foo']; // It triggers a deprecation
                    })
                    ->setNormalizer('bar', function (Options $options, $value) {
                        $options['foo']; // It triggers a deprecation
                        $options['foo']; // It triggers a deprecation

                        return $value;
                    })
                ;
            },
            ['foo' => 'baz'], // It triggers a deprecation
            [
                'type' => E_USER_DEPRECATED,
                'message' => 'The option "foo" is deprecated.',
            ],
            4,
        ];

        yield 'It ignores a deprecation if no option is provided by the user' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined('foo')
                    ->setDefault('bar', null)
                    ->setDeprecated('foo')
                    ->setDeprecated('bar')
                ;
            },
            [],
            null,
            0,
        ];

        yield 'It explicitly ignores a depreciation' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefault('baz', function (Options $options) {
                        return $options->offsetGet('foo', false);
                    })
                    ->setDefault('foo', null)
                    ->setDeprecated('foo')
                    ->setDefault('bar', function (Options $options) {
                        return $options->offsetGet('foo', false);
                    })
                ;
            },
            [],
            null,
            0,
        ];
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetAllowedTypesFailsIfUnknownOption()
    {
        $this->resolver->setAllowedTypes('foo', 'string');
    }

    public function testResolveTypedArray()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[]');
        $options = $this->resolver->resolve(['foo' => ['bar', 'baz']]);

        $this->assertSame(['foo' => ['bar', 'baz']], $options);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetAllowedTypesFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[]", but one of the elements is of type "DateTime[]".
     */
    public function testResolveFailsIfInvalidTypedArray()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(['foo' => [new \DateTime()]]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value "bar" is expected to be of type "int[]", but is of type "string".
     */
    public function testResolveFailsWithNonArray()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(['foo' => 'bar']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[]", but one of the elements is of type "stdClass[]".
     */
    public function testResolveFailsIfTypedArrayContainsInvalidTypes()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');
        $values = range(1, 5);
        $values[] = new \stdClass();
        $values[] = [];
        $values[] = new \DateTime();
        $values[] = 123;

        $this->resolver->resolve(['foo' => $values]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "double[][]".
     */
    public function testResolveFailsWithCorrectLevelsButWrongScalar()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');

        $this->resolver->resolve([
            'foo' => [
                [1.2],
            ],
        ]);
    }

    /**
     * @dataProvider provideInvalidTypes
     */
    public function testResolveFailsIfInvalidType($actualType, $allowedType, $exceptionMessage)
    {
        $this->resolver->setDefined('option');
        $this->resolver->setAllowedTypes('option', $allowedType);

        if (method_exists($this, 'expectException')) {
            $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
            $this->expectExceptionMessage($exceptionMessage);
        } else {
            $this->setExpectedException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException', $exceptionMessage);
        }

        $this->resolver->resolve(['option' => $actualType]);
    }

    public function provideInvalidTypes()
    {
        return [
            [true, 'string', 'The option "option" with value true is expected to be of type "string", but is of type "boolean".'],
            [false, 'string', 'The option "option" with value false is expected to be of type "string", but is of type "boolean".'],
            [fopen(__FILE__, 'r'), 'string', 'The option "option" with value resource is expected to be of type "string", but is of type "resource".'],
            [[], 'string', 'The option "option" with value array is expected to be of type "string", but is of type "array".'],
            [new OptionsResolver(), 'string', 'The option "option" with value Symfony\Component\OptionsResolver\OptionsResolver is expected to be of type "string", but is of type "Symfony\Component\OptionsResolver\OptionsResolver".'],
            [42, 'string', 'The option "option" with value 42 is expected to be of type "string", but is of type "integer".'],
            [null, 'string', 'The option "option" with value null is expected to be of type "string", but is of type "NULL".'],
            ['bar', '\stdClass', 'The option "option" with value "bar" is expected to be of type "\stdClass", but is of type "string".'],
        ];
    }

    public function testResolveSucceedsIfValidType()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value 42 is expected to be of type "string" or "bool", but is of type "integer".
     */
    public function testResolveFailsIfInvalidTypeMultiple()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedTypes('foo', ['string', 'bool']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidTypeMultiple()
    {
        $this->resolver->setDefault('foo', true);
        $this->resolver->setAllowedTypes('foo', ['string', 'bool']);

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfInstanceOfClass()
    {
        $this->resolver->setDefault('foo', new \stdClass());
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfTypedArray()
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedTypes('foo', ['null', 'DateTime[]']);

        $data = [
            'foo' => [
                new \DateTime(),
                new \DateTime(),
            ],
        ];
        $result = $this->resolver->resolve($data);
        $this->assertEquals($data, $result);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfNotInstanceOfClass()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testAddAllowedTypesFailsIfUnknownOption()
    {
        $this->resolver->addAllowedTypes('foo', 'string');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfAddAllowedTypesFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->addAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedType()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', 'string');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedType()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', 'string');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedTypeMultiple()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', ['string', 'bool']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedTypeMultiple()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', ['string', 'bool']);

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', 'bar');

        $this->assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite2()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', false);

        $this->assertNotEmpty($this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetAllowedValuesFailsIfUnknownOption()
    {
        $this->resolver->setAllowedValues('foo', 'bar');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetAllowedValuesFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value 42 is invalid. Accepted values are: "bar".
     */
    public function testResolveFailsIfInvalidValue()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve(['foo' => 42]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value null is invalid. Accepted values are: "bar".
     */
    public function testResolveFailsIfInvalidValueIsNull()
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidValueStrict()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', '42');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValue()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidValueIsNull()
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', null);

        $this->assertEquals(['foo' => null], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value 42 is invalid. Accepted values are: "bar", false, null.
     */
    public function testResolveFailsIfInvalidValueMultiple()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', ['bar', false, null]);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValueMultiple()
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', ['bar', 'baz']);

        $this->assertEquals(['foo' => 'baz'], $this->resolver->resolve());
    }

    public function testResolveFailsIfClosureReturnsFalse()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', function ($value) use (&$passedValue) {
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
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function ($value) use (&$passedValue) {
            $passedValue = $value;

            return true;
        });

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
        $this->assertSame('bar', $passedValue);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfAllClosuresReturnFalse()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', [
            function () { return false; },
            function () { return false; },
            function () { return false; },
        ]);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyClosureReturnsTrue()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', [
            function () { return false; },
            function () { return true; },
            function () { return false; },
        ]);

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testAddAllowedValuesFailsIfUnknownOption()
    {
        $this->resolver->addAllowedValues('foo', 'bar');
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfAddAllowedValuesFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->addAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedValue()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValue()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'bar');

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidAddedValueIsNull()
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->addAllowedValues('foo', null);

        $this->assertEquals(['foo' => null], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfInvalidAddedValueMultiple()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', ['bar', 'baz']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValueMultiple()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', ['bar', 'baz']);

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite2()
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        $this->assertEquals(['foo' => 'baz'], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testResolveFailsIfAllAddedClosuresReturnFalse()
    {
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', function () { return false; });
        $this->resolver->addAllowedValues('foo', function () { return false; });

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function () { return false; });
        $this->resolver->addAllowedValues('foo', function () { return true; });

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue2()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function () { return true; });
        $this->resolver->addAllowedValues('foo', function () { return false; });

        $this->assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testSetNormalizerReturnsThis()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->assertSame($this->resolver, $this->resolver->setNormalizer('foo', function () {}));
    }

    public function testSetNormalizerClosure()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function () {
            return 'normalized';
        });

        $this->assertEquals(['foo' => 'normalized'], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     */
    public function testSetNormalizerFailsIfUnknownOption()
    {
        $this->resolver->setNormalizer('foo', function () {});
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetNormalizerFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setNormalizer('foo', function () {});
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testNormalizerReceivesSetOption()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $this->assertEquals(['foo' => 'normalized[bar]'], $this->resolver->resolve());
    }

    public function testNormalizerReceivesPassedOption()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $resolved = $this->resolver->resolve(['foo' => 'baz']);

        $this->assertEquals(['foo' => 'normalized[baz]'], $resolved);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateTypeBeforeNormalization()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setAllowedTypes('foo', 'int');

        $this->resolver->setNormalizer('foo', function () {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testValidateValueBeforeNormalization()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setAllowedValues('foo', 'baz');

        $this->resolver->setNormalizer('foo', function () {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    public function testNormalizerCanAccessOtherOptions()
    {
        $this->resolver->setDefault('default', 'bar');
        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', function (Options $options) {
            /* @var TestCase $test */
            Assert::assertSame('bar', $options['default']);

            return 'normalized';
        });

        $this->assertEquals([
            'default' => 'bar',
            'norm' => 'normalized',
        ], $this->resolver->resolve());
    }

    public function testNormalizerCanAccessLazyOptions()
    {
        $this->resolver->setDefault('lazy', function (Options $options) {
            return 'bar';
        });
        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', function (Options $options) {
            /* @var TestCase $test */
            Assert::assertEquals('bar', $options['lazy']);

            return 'normalized';
        });

        $this->assertEquals([
            'lazy' => 'bar',
            'norm' => 'normalized',
        ], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependencyBetweenNormalizers()
    {
        $this->resolver->setDefault('norm1', 'bar');
        $this->resolver->setDefault('norm2', 'baz');

        $this->resolver->setNormalizer('norm1', function (Options $options) {
            $options['norm2'];
        });

        $this->resolver->setNormalizer('norm2', function (Options $options) {
            $options['norm1'];
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependencyBetweenNormalizerAndLazyOption()
    {
        $this->resolver->setDefault('lazy', function (Options $options) {
            $options['norm'];
        });

        $this->resolver->setDefault('norm', 'baz');

        $this->resolver->setNormalizer('norm', function (Options $options) {
            $options['lazy'];
        });

        $this->resolver->resolve();
    }

    public function testCaughtExceptionFromNormalizerDoesNotCrashOptionResolver()
    {
        $throw = true;

        $this->resolver->setDefaults(['catcher' => null, 'thrower' => null]);

        $this->resolver->setNormalizer('catcher', function (Options $options) {
            try {
                return $options['thrower'];
            } catch (\Exception $e) {
                return false;
            }
        });

        $this->resolver->setNormalizer('thrower', function () use (&$throw) {
            if ($throw) {
                $throw = false;
                throw new \UnexpectedValueException('throwing');
            }

            return true;
        });

        $this->assertSame(['catcher' => false, 'thrower' => true], $this->resolver->resolve());
    }

    public function testCaughtExceptionFromLazyDoesNotCrashOptionResolver()
    {
        $throw = true;

        $this->resolver->setDefault('catcher', function (Options $options) {
            try {
                return $options['thrower'];
            } catch (\Exception $e) {
                return false;
            }
        });

        $this->resolver->setDefault('thrower', function (Options $options) use (&$throw) {
            if ($throw) {
                $throw = false;
                throw new \UnexpectedValueException('throwing');
            }

            return true;
        });

        $this->assertSame(['catcher' => false, 'thrower' => true], $this->resolver->resolve());
    }

    public function testInvokeEachNormalizerOnlyOnce()
    {
        $calls = 0;

        $this->resolver->setDefault('norm1', 'bar');
        $this->resolver->setDefault('norm2', 'baz');

        $this->resolver->setNormalizer('norm1', function ($options) use (&$calls) {
            Assert::assertSame(1, ++$calls);

            $options['norm2'];
        });
        $this->resolver->setNormalizer('norm2', function () use (&$calls) {
            Assert::assertSame(2, ++$calls);
        });

        $this->resolver->resolve();

        $this->assertSame(2, $calls);
    }

    public function testNormalizerNotCalledForUnsetOptions()
    {
        $this->resolver->setDefined('norm');

        $this->resolver->setNormalizer('norm', function () {
            Assert::fail('Should not be called.');
        });

        $this->assertEmpty($this->resolver->resolve());
    }

    public function testSetDefaultsReturnsThis()
    {
        $this->assertSame($this->resolver, $this->resolver->setDefaults(['foo', 'bar']));
    }

    public function testSetDefaults()
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', 'bar');

        $this->resolver->setDefaults([
            'two' => '2',
            'three' => '3',
        ]);

        $this->assertEquals([
            'one' => '1',
            'two' => '2',
            'three' => '3',
        ], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfSetDefaultsFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setDefaults(['two' => '2']);
        });

        $this->resolver->resolve();
    }

    public function testRemoveReturnsThis()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame($this->resolver, $this->resolver->remove('foo'));
    }

    public function testRemoveSingleOption()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->remove('foo');

        $this->assertSame(['baz' => 'boo'], $this->resolver->resolve());
    }

    public function testRemoveMultipleOptions()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->setDefault('doo', 'dam');

        $this->resolver->remove(['foo', 'doo']);

        $this->assertSame(['baz' => 'boo'], $this->resolver->resolve());
    }

    public function testRemoveLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });
        $this->resolver->remove('foo');

        $this->assertSame([], $this->resolver->resolve());
    }

    public function testRemoveNormalizer()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testRemoveAllowedTypes()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testRemoveAllowedValues()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', ['baz', 'boo']);
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfRemoveFromLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->remove('bar');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testRemoveUnknownOptionIgnored()
    {
        $this->assertNotNull($this->resolver->remove('foo'));
    }

    public function testClearReturnsThis()
    {
        $this->assertSame($this->resolver, $this->resolver->clear());
    }

    public function testClearRemovesAllOptions()
    {
        $this->resolver->setDefault('one', 1);
        $this->resolver->setDefault('two', 2);

        $this->resolver->clear();

        $this->assertEmpty($this->resolver->resolve());
    }

    public function testClearLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });
        $this->resolver->clear();

        $this->assertSame([], $this->resolver->resolve());
    }

    public function testClearNormalizer()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testClearAllowedTypes()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testClearAllowedValues()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'baz');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testFailIfClearFromLazyption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->clear();
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testClearOptionAndNormalizer()
    {
        $this->resolver->setDefault('foo1', 'bar');
        $this->resolver->setNormalizer('foo1', function (Options $options) {
            return '';
        });
        $this->resolver->setDefault('foo2', 'bar');
        $this->resolver->setNormalizer('foo2', function (Options $options) {
            return '';
        });

        $this->resolver->clear();
        $this->assertEmpty($this->resolver->resolve());
    }

    public function testArrayAccess()
    {
        $this->resolver->setDefault('default1', 0);
        $this->resolver->setDefault('default2', 1);
        $this->resolver->setRequired('required');
        $this->resolver->setDefined('defined');
        $this->resolver->setDefault('lazy1', function (Options $options) {
            return 'lazy';
        });

        $this->resolver->setDefault('lazy2', function (Options $options) {
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

            // Obviously $options['lazy'] and $options['defined'] cannot be
            // accessed
        });

        $this->resolver->resolve(['default2' => 42, 'required' => 'value']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessGetFailsOutsideResolve()
    {
        $this->resolver->setDefault('default', 0);

        $this->resolver['default'];
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessExistsFailsOutsideResolve()
    {
        $this->resolver->setDefault('default', 0);

        isset($this->resolver['default']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessSetNotSupported()
    {
        $this->resolver['default'] = 0;
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testArrayAccessUnsetNotSupported()
    {
        $this->resolver->setDefault('default', 0);

        unset($this->resolver['default']);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoSuchOptionException
     * @expectedExceptionMessage The option "undefined" does not exist. Defined options are: "foo", "lazy".
     */
    public function testFailIfGetNonExisting()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setDefault('lazy', function (Options $options) {
            $options['undefined'];
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\NoSuchOptionException
     * @expectedExceptionMessage The optional option "defined" has no value set. You should make sure it is set with "isset" before reading it.
     */
    public function testFailIfGetDefinedButUnset()
    {
        $this->resolver->setDefined('defined');

        $this->resolver->setDefault('lazy', function (Options $options) {
            $options['defined'];
        });

        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailIfCyclicDependency()
    {
        $this->resolver->setDefault('lazy1', function (Options $options) {
            $options['lazy2'];
        });

        $this->resolver->setDefault('lazy2', function (Options $options) {
            $options['lazy1'];
        });

        $this->resolver->resolve();
    }

    public function testCount()
    {
        $this->resolver->setDefault('default', 0);
        $this->resolver->setRequired('required');
        $this->resolver->setDefined('defined');
        $this->resolver->setDefault('lazy1', function () {});

        $this->resolver->setDefault('lazy2', function (Options $options) {
            Assert::assertCount(4, $options);
        });

        $this->assertCount(4, $this->resolver->resolve(['required' => 'value']));
    }

    /**
     * In resolve() we count the options that are actually set (which may be
     * only a subset of the defined options). Outside of resolve(), it's not
     * clear what is counted.
     *
     * @expectedException \Symfony\Component\OptionsResolver\Exception\AccessException
     */
    public function testCountFailsOutsideResolve()
    {
        $this->resolver->setDefault('foo', 0);
        $this->resolver->setRequired('bar');
        $this->resolver->setDefined('bar');
        $this->resolver->setDefault('lazy1', function () {});

        \count($this->resolver);
    }

    public function testNestedArrays()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');

        $this->assertEquals([
            'foo' => [
                [
                    1, 2,
                ],
            ],
        ], $this->resolver->resolve([
            'foo' => [
                [1, 2],
            ],
        ]));
    }

    public function testNested2Arrays()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][][][]');

        $this->assertEquals([
            'foo' => [
                [
                    [
                        [
                            1, 2,
                        ],
                    ],
                ],
            ],
        ], $this->resolver->resolve(
            [
                'foo' => [
                    [
                        [
                            [1, 2],
                        ],
                    ],
                ],
            ]
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "float[][][][]", but one of the elements is of type "integer[][][][]".
     */
    public function testNestedArraysException()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'float[][][][]');

        $this->resolver->resolve([
            'foo' => [
                [
                    [
                        [1, 2],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "boolean[][]".
     */
    public function testNestedArrayException1()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');
        $this->resolver->resolve([
            'foo' => [
                [1, true, 'str', [2, 3]],
            ],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "boolean[][]".
     */
    public function testNestedArrayException2()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');
        $this->resolver->resolve([
            'foo' => [
                [true, 'str', [2, 3]],
            ],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "string[][][]", but one of the elements is of type "string[][]".
     */
    public function testNestedArrayException3()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[][][]');
        $this->resolver->resolve([
            'foo' => [
                ['str', [1, 2]],
            ],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "string[][][]", but one of the elements is of type "integer[][][]".
     */
    public function testNestedArrayException4()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[][][]');
        $this->resolver->resolve([
            'foo' => [
                [
                    ['str'], [1, 2], ],
            ],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "foo" with value array is expected to be of type "string[]", but one of the elements is of type "array[]".
     */
    public function testNestedArrayException5()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[]');
        $this->resolver->resolve([
            'foo' => [
                [
                    ['str'], [1, 2], ],
            ],
        ]);
    }

    public function testIsNestedOption()
    {
        $this->resolver->setDefaults([
            'database' => function (OptionsResolver $resolver) {
                $resolver->setDefined(['host', 'port']);
            },
        ]);
        $this->assertTrue($this->resolver->isNested('database'));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException
     * @expectedExceptionMessage The option "foo" does not exist. Defined options are: "host", "port".
     */
    public function testFailsIfUndefinedNestedOption()
    {
        $this->resolver->setDefaults([
            'name' => 'default',
            'database' => function (OptionsResolver $resolver) {
                $resolver->setDefined(['host', 'port']);
            },
        ]);
        $this->resolver->resolve([
            'database' => ['foo' => 'bar'],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\MissingOptionsException
     * @expectedExceptionMessage The required option "host" is missing.
     */
    public function testFailsIfMissingRequiredNestedOption()
    {
        $this->resolver->setDefaults([
            'name' => 'default',
            'database' => function (OptionsResolver $resolver) {
                $resolver->setRequired('host');
            },
        ]);
        $this->resolver->resolve([
            'database' => [],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The option "logging" with value null is expected to be of type "bool", but is of type "NULL".
     */
    public function testFailsIfInvalidTypeNestedOption()
    {
        $this->resolver->setDefaults([
            'name' => 'default',
            'database' => function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined('logging')
                    ->setAllowedTypes('logging', 'bool');
            },
        ]);
        $this->resolver->resolve([
            'database' => ['logging' => null],
        ]);
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     * @expectedExceptionMessage The nested option "database" with value null is expected to be of type array, but is of type "NULL".
     */
    public function testFailsIfNotArrayIsGivenForNestedOptions()
    {
        $this->resolver->setDefaults([
            'name' => 'default',
            'database' => function (OptionsResolver $resolver) {
                $resolver->setDefined('host');
            },
        ]);
        $this->resolver->resolve([
            'database' => null,
        ]);
    }

    public function testResolveNestedOptionsWithoutDefault()
    {
        $this->resolver->setDefaults([
            'name' => 'default',
            'database' => function (OptionsResolver $resolver) {
                $resolver->setDefined(['host', 'port']);
            },
        ]);
        $actualOptions = $this->resolver->resolve();
        $expectedOptions = [
            'name' => 'default',
            'database' => [],
        ];
        $this->assertSame($expectedOptions, $actualOptions);
    }

    public function testResolveNestedOptionsWithDefault()
    {
        $this->resolver->setDefaults([
            'name' => 'default',
            'database' => function (OptionsResolver $resolver) {
                $resolver->setDefaults([
                    'host' => 'localhost',
                    'port' => 3306,
                ]);
            },
        ]);
        $actualOptions = $this->resolver->resolve();
        $expectedOptions = [
            'name' => 'default',
            'database' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ];
        $this->assertSame($expectedOptions, $actualOptions);
    }

    public function testResolveMultipleNestedOptions()
    {
        $this->resolver->setDefaults([
            'name' => 'default',
            'database' => function (OptionsResolver $resolver) {
                $resolver
                    ->setRequired(['dbname', 'host'])
                    ->setDefaults([
                        'port' => 3306,
                        'replicas' => function (OptionsResolver $resolver) {
                            $resolver->setDefaults([
                                'host' => 'replica1',
                                'port' => 3306,
                            ]);
                        },
                    ]);
            },
        ]);
        $actualOptions = $this->resolver->resolve([
            'name' => 'custom',
            'database' => [
                'dbname' => 'test',
                'host' => 'localhost',
                'port' => null,
                'replicas' => ['host' => 'replica2'],
            ],
        ]);
        $expectedOptions = [
            'name' => 'custom',
            'database' => [
                'port' => null,
                'replicas' => ['port' => 3306, 'host' => 'replica2'],
                'dbname' => 'test',
                'host' => 'localhost',
            ],
        ];
        $this->assertSame($expectedOptions, $actualOptions);
    }

    public function testResolveLazyOptionUsingNestedOption()
    {
        $this->resolver->setDefaults([
            'version' => function (Options $options) {
                return $options['database']['server_version'];
            },
            'database' => function (OptionsResolver $resolver) {
                $resolver->setDefault('server_version', '3.15');
            },
        ]);
        $actualOptions = $this->resolver->resolve();
        $expectedOptions = [
            'database' => ['server_version' => '3.15'],
            'version' => '3.15',
        ];
        $this->assertSame($expectedOptions, $actualOptions);
    }

    public function testNormalizeNestedOptionValue()
    {
        $this->resolver
            ->setDefaults([
                'database' => function (OptionsResolver $resolver) {
                    $resolver->setDefaults([
                        'port' => 3306,
                        'host' => 'localhost',
                        'dbname' => 'demo',
                    ]);
                },
            ])
            ->setNormalizer('database', function (Options $options, $value) {
                ksort($value);

                return $value;
            });
        $actualOptions = $this->resolver->resolve([
            'database' => ['dbname' => 'test'],
        ]);
        $expectedOptions = [
            'database' => ['dbname' => 'test', 'host' => 'localhost', 'port' => 3306],
        ];
        $this->assertSame($expectedOptions, $actualOptions);
    }

    public function testOverwrittenNestedOptionNotEvaluatedIfLazyDefault()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            Assert::fail('Should not be called');
        });
        // defined by subclass
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });
        $this->assertSame(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testOverwrittenNestedOptionNotEvaluatedIfScalarDefault()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            Assert::fail('Should not be called');
        });
        // defined by subclass
        $this->resolver->setDefault('foo', 'bar');
        $this->assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testOverwrittenLazyOptionNotEvaluatedIfNestedOption()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (Options $options) {
            Assert::fail('Should not be called');
        });
        // defined by subclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            $resolver->setDefault('bar', 'baz');
        });
        $this->assertSame(['foo' => ['bar' => 'baz']], $this->resolver->resolve());
    }

    public function testResolveAllNestedOptionDefinitions()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            $resolver->setRequired('bar');
        });
        // defined by subclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            $resolver->setDefault('bar', 'baz');
        });
        // defined by subclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            $resolver->setDefault('ping', 'pong');
        });
        $this->assertSame(['foo' => ['ping' => 'pong', 'bar' => 'baz']], $this->resolver->resolve());
    }

    public function testNormalizeNestedValue()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            $resolver->setDefault('bar', null);
        });
        // defined by subclass
        $this->resolver->setNormalizer('foo', function (Options $options, $resolvedValue) {
            if (null === $resolvedValue['bar']) {
                $resolvedValue['bar'] = 'baz';
            }

            return $resolvedValue;
        });
        $this->assertSame(['foo' => ['bar' => 'baz']], $this->resolver->resolve());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailsIfCyclicDependencyBetweenSameNestedOption()
    {
        $this->resolver->setDefault('database', function (OptionsResolver $resolver, Options $parent) {
            $resolver->setDefault('replicas', $parent['database']);
        });
        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailsIfCyclicDependencyBetweenNestedOptionAndParentLazyOption()
    {
        $this->resolver->setDefaults([
            'version' => function (Options $options) {
                return $options['database']['server_version'];
            },
            'database' => function (OptionsResolver $resolver, Options $parent) {
                $resolver->setDefault('server_version', $parent['version']);
            },
        ]);
        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailsIfCyclicDependencyBetweenNormalizerAndNestedOption()
    {
        $this->resolver
            ->setDefault('name', 'default')
            ->setDefault('database', function (OptionsResolver $resolver, Options $parent) {
                $resolver->setDefault('host', $parent['name']);
            })
            ->setNormalizer('name', function (Options $options, $value) {
                $options['database'];
            });
        $this->resolver->resolve();
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\OptionDefinitionException
     */
    public function testFailsIfCyclicDependencyBetweenNestedOptions()
    {
        $this->resolver->setDefault('database', function (OptionsResolver $resolver, Options $parent) {
            $resolver->setDefault('host', $parent['replica']['host']);
        });
        $this->resolver->setDefault('replica', function (OptionsResolver $resolver, Options $parent) {
            $resolver->setDefault('host', $parent['database']['host']);
        });
        $this->resolver->resolve();
    }

    public function testGetAccessToParentOptionFromNestedOption()
    {
        $this->resolver->setDefaults([
            'version' => 3.15,
            'database' => function (OptionsResolver $resolver, Options $parent) {
                $resolver->setDefault('server_version', $parent['version']);
            },
        ]);
        $this->assertSame(['version' => 3.15, 'database' => ['server_version' => 3.15]], $this->resolver->resolve());
    }

    public function testNestedClosureWithoutTypeHintNotInvoked()
    {
        $closure = function ($resolver) {
            Assert::fail('Should not be called');
        };
        $this->resolver->setDefault('foo', $closure);
        $this->assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testNestedClosureWithoutTypeHint2ndArgumentNotInvoked()
    {
        $closure = function (OptionsResolver $resolver, $parent) {
            Assert::fail('Should not be called');
        };
        $this->resolver->setDefault('foo', $closure);
        $this->assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testResolveLazyOptionWithTransitiveDefaultDependency()
    {
        $this->resolver->setDefaults([
            'ip' => null,
            'database' => function (OptionsResolver $resolver, Options $parent) {
                $resolver->setDefault('host', $parent['ip']);
                $resolver->setDefault('primary_replica', function (OptionsResolver $resolver, Options $parent) {
                    $resolver->setDefault('host', $parent['host']);
                });
            },
            'secondary_replica' => function (Options $options) {
                return $options['database']['primary_replica']['host'];
            },
        ]);
        $actualOptions = $this->resolver->resolve(['ip' => '127.0.0.1']);
        $expectedOptions = [
            'ip' => '127.0.0.1',
            'database' => [
                'host' => '127.0.0.1',
                'primary_replica' => ['host' => '127.0.0.1'],
            ],
            'secondary_replica' => '127.0.0.1',
        ];
        $this->assertSame($expectedOptions, $actualOptions);
    }

    public function testAccessToParentOptionFromNestedNormalizerAndLazyOption()
    {
        $this->resolver->setDefaults([
            'debug' => true,
            'database' => function (OptionsResolver $resolver, Options $parent) {
                $resolver
                    ->setDefined('logging')
                    ->setDefault('profiling', function (Options $options) use ($parent) {
                        return $parent['debug'];
                    })
                    ->setNormalizer('logging', function (Options $options, $value) use ($parent) {
                        return false === $parent['debug'] ? true : $value;
                    });
            },
        ]);
        $actualOptions = $this->resolver->resolve([
            'debug' => false,
            'database' => ['logging' => false],
        ]);
        $expectedOptions = [
            'debug' => false,
            'database' => ['profiling' => false, 'logging' => true],
        ];
        $this->assertSame($expectedOptions, $actualOptions);
    }
}
