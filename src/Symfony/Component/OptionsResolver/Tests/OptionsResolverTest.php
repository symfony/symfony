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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\OptionsResolver\Debug\OptionsResolverIntrospector;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\NoSuchOptionException;
use Symfony\Component\OptionsResolver\Exception\OptionDefinitionException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OptionsResolverTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @var OptionsResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OptionsResolver();
    }

    public function testResolveFailsIfNonExistingOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "foo" does not exist. Defined options are: "a", "z".');
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(['foo' => 'bar']);
    }

    public function testResolveFailsIfMultipleNonExistingOptions()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The options "baz", "foo", "ping" do not exist. Defined options are: "a", "z".');
        $this->resolver->setDefault('z', '1');
        $this->resolver->setDefault('a', '2');

        $this->resolver->resolve(['ping' => 'pong', 'foo' => 'bar', 'baz' => 'bam']);
    }

    public function testResolveFailsFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->resolve([]);
        });

        $this->resolver->resolve();
    }

    public function testSetDefaultReturnsThis()
    {
        self::assertSame($this->resolver, $this->resolver->setDefault('foo', 'bar'));
    }

    public function testSetDefault()
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', '20');

        self::assertEquals([
            'one' => '1',
            'two' => '20',
        ], $this->resolver->resolve());
    }

    public function testFailIfSetDefaultFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('lazy', function (Options $options) {
            $options->setDefault('default', 42);
        });

        $this->resolver->resolve();
    }

    public function testHasDefault()
    {
        self::assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', 42);
        self::assertTrue($this->resolver->hasDefault('foo'));
    }

    public function testHasDefaultWithNullValue()
    {
        self::assertFalse($this->resolver->hasDefault('foo'));
        $this->resolver->setDefault('foo', null);
        self::assertTrue($this->resolver->hasDefault('foo'));
    }

    public function testSetLazyReturnsThis()
    {
        self::assertSame($this->resolver, $this->resolver->setDefault('foo', function (Options $options) {}));
    }

    public function testSetLazyClosure()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });

        self::assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testClosureWithoutTypeHintNotInvoked()
    {
        $closure = function ($options) {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        self::assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testClosureWithoutParametersNotInvoked()
    {
        $closure = function () {
            Assert::fail('Should not be called');
        };

        $this->resolver->setDefault('foo', $closure);

        self::assertSame(['foo' => $closure], $this->resolver->resolve());
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

        self::assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
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

        self::assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
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

        self::assertEquals(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testOverwrittenLazyOptionNotEvaluated()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            Assert::fail('Should not be called');
        });

        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
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

        self::assertSame(2, $calls);
    }

    public function testSetRequiredReturnsThis()
    {
        self::assertSame($this->resolver, $this->resolver->setRequired('foo'));
    }

    public function testFailIfSetRequiredFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setRequired('bar');
        });

        $this->resolver->resolve();
    }

    public function testResolveFailsIfRequiredOptionMissing()
    {
        self::expectException(MissingOptionsException::class);
        $this->resolver->setRequired('foo');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfRequiredOptionSet()
    {
        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        self::assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfRequiredOptionPassed()
    {
        $this->resolver->setRequired('foo');

        self::assertNotEmpty($this->resolver->resolve(['foo' => 'bar']));
    }

    public function testIsRequired()
    {
        self::assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        self::assertTrue($this->resolver->isRequired('foo'));
    }

    public function testRequiredIfSetBefore()
    {
        self::assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setRequired('foo');

        self::assertTrue($this->resolver->isRequired('foo'));
    }

    public function testStillRequiredAfterSet()
    {
        self::assertFalse($this->resolver->isRequired('foo'));

        $this->resolver->setRequired('foo');
        $this->resolver->setDefault('foo', 'bar');

        self::assertTrue($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterRemove()
    {
        self::assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        self::assertFalse($this->resolver->isRequired('foo'));
    }

    public function testIsNotRequiredAfterClear()
    {
        self::assertFalse($this->resolver->isRequired('foo'));
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        self::assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetRequiredOptions()
    {
        $this->resolver->setRequired(['foo', 'bar']);
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        self::assertSame(['foo', 'bar'], $this->resolver->getRequiredOptions());
    }

    public function testIsMissingIfNotSet()
    {
        self::assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        self::assertTrue($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingIfSet()
    {
        $this->resolver->setDefault('foo', 'bar');

        self::assertFalse($this->resolver->isMissing('foo'));
        $this->resolver->setRequired('foo');
        self::assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterRemove()
    {
        $this->resolver->setRequired('foo');
        $this->resolver->remove('foo');
        self::assertFalse($this->resolver->isMissing('foo'));
    }

    public function testIsNotMissingAfterClear()
    {
        $this->resolver->setRequired('foo');
        $this->resolver->clear();
        self::assertFalse($this->resolver->isRequired('foo'));
    }

    public function testGetMissingOptions()
    {
        $this->resolver->setRequired(['foo', 'bar']);
        $this->resolver->setDefault('bam', 'baz');
        $this->resolver->setDefault('foo', 'boo');

        self::assertSame(['bar'], $this->resolver->getMissingOptions());
    }

    public function testFailIfSetDefinedFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setDefined('bar');
        });

        $this->resolver->resolve();
    }

    public function testDefinedOptionsNotIncludedInResolvedOptions()
    {
        $this->resolver->setDefined('foo');

        self::assertSame([], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetBefore()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefined('foo');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfDefaultSetAfter()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testDefinedOptionsIncludedIfPassedToResolve()
    {
        $this->resolver->setDefined('foo');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve(['foo' => 'bar']));
    }

    public function testIsDefined()
    {
        self::assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        self::assertTrue($this->resolver->isDefined('foo'));
    }

    public function testLazyOptionsAreDefined()
    {
        self::assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', function (Options $options) {});
        self::assertTrue($this->resolver->isDefined('foo'));
    }

    public function testRequiredOptionsAreDefined()
    {
        self::assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setRequired('foo');
        self::assertTrue($this->resolver->isDefined('foo'));
    }

    public function testSetOptionsAreDefined()
    {
        self::assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefault('foo', 'bar');
        self::assertTrue($this->resolver->isDefined('foo'));
    }

    public function testGetDefinedOptions()
    {
        $this->resolver->setDefined(['foo', 'bar']);
        $this->resolver->setDefault('baz', 'bam');
        $this->resolver->setRequired('boo');

        self::assertSame(['foo', 'bar', 'baz', 'boo'], $this->resolver->getDefinedOptions());
    }

    public function testRemovedOptionsAreNotDefined()
    {
        self::assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        self::assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->remove('foo');
        self::assertFalse($this->resolver->isDefined('foo'));
    }

    public function testClearedOptionsAreNotDefined()
    {
        self::assertFalse($this->resolver->isDefined('foo'));
        $this->resolver->setDefined('foo');
        self::assertTrue($this->resolver->isDefined('foo'));
        $this->resolver->clear();
        self::assertFalse($this->resolver->isDefined('foo'));
    }

    public function testFailIfSetDeprecatedFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver
            ->setDefault('bar', 'baz')
            ->setDefault('foo', function (Options $options) {
                $options->setDeprecated('bar');
            })
            ->resolve()
        ;
    }

    public function testSetDeprecatedFailsIfUnknownOption()
    {
        self::expectException(UndefinedOptionsException::class);
        $this->resolver->setDeprecated('foo');
    }

    public function testSetDeprecatedFailsIfInvalidDeprecationMessageType()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid type for deprecation message argument, expected string or \Closure, but got "bool".');
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo', 'vendor/package', '1.1', true)
        ;
    }

    public function testLazyDeprecationFailsIfInvalidDeprecationMessageType()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid type for deprecation message, expected string but got "bool", return an empty string to ignore.');
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo', 'vendor/package', '1.1', function (Options $options, $value) {
                return false;
            })
        ;
        $this->resolver->resolve(['foo' => null]);
    }

    public function testFailsIfCyclicDependencyBetweenDeprecation()
    {
        self::expectException(OptionDefinitionException::class);
        self::expectExceptionMessage('The options "foo", "bar" have a cyclic dependency.');
        $this->resolver
            ->setDefined(['foo', 'bar'])
            ->setDeprecated('foo', 'vendor/package', '1.1', function (Options $options, $value) {
                $options['bar'];
            })
            ->setDeprecated('bar', 'vendor/package', '1.1', function (Options $options, $value) {
                $options['foo'];
            })
        ;
        $this->resolver->resolve(['foo' => null, 'bar' => null]);
    }

    public function testIsDeprecated()
    {
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo', 'vendor/package', '1.1')
        ;
        self::assertTrue($this->resolver->isDeprecated('foo'));
    }

    public function testIsNotDeprecatedIfEmptyString()
    {
        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo', 'vendor/package', '1.1', '')
        ;
        self::assertFalse($this->resolver->isDeprecated('foo'));
    }

    /**
     * @dataProvider provideDeprecationData
     */
    public function testDeprecationMessages(\Closure $configureOptions, array $options, ?array $expectedError, int $expectedCount)
    {
        $count = 0;
        error_clear_last();
        set_error_handler(function (int $type) use (&$count) {
            self::assertSame(\E_USER_DEPRECATED, $type);

            ++$count;

            return false;
        });
        $e = error_reporting(0);

        try {
            $configureOptions($this->resolver);
            $this->resolver->resolve($options);
        } finally {
            error_reporting($e);
            restore_error_handler();
        }

        $lastError = error_get_last();
        unset($lastError['file'], $lastError['line']);

        self::assertSame($expectedError, $lastError);
        self::assertSame($expectedCount, $count);
    }

    public function provideDeprecationData()
    {
        yield 'It deprecates an option with default message' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined(['foo', 'bar'])
                    ->setDeprecated('foo', 'vendor/package', '1.1', 'The option "%name%" is deprecated.')
                ;
            },
            ['foo' => 'baz'],
            [
                'type' => \E_USER_DEPRECATED,
                'message' => 'Since vendor/package 1.1: The option "foo" is deprecated.',
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
                    ->setDeprecated('foo', 'vendor/package', '1.1', 'The option "foo" is deprecated, use "bar" option instead.')
                ;
            },
            ['foo' => 'baz'],
            [
                'type' => \E_USER_DEPRECATED,
                'message' => 'Since vendor/package 1.1: The option "foo" is deprecated, use "bar" option instead.',
            ],
            2,
        ];

        yield 'It deprecates an option evaluated in another definition' => [
            function (OptionsResolver $resolver) {
                // defined by superclass
                $resolver
                    ->setDefault('foo', null)
                    ->setDeprecated('foo', 'vendor/package', '1.1', 'The option "%name%" is deprecated.')
                ;
                // defined by subclass
                $resolver->setDefault('bar', function (Options $options) {
                    return $options['foo']; // It triggers a deprecation
                });
            },
            [],
            [
                'type' => \E_USER_DEPRECATED,
                'message' => 'Since vendor/package 1.1: The option "foo" is deprecated.',
            ],
            1,
        ];

        yield 'It deprecates allowed type and value' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefault('foo', null)
                    ->setAllowedTypes('foo', ['null', 'string', \stdClass::class])
                    ->setDeprecated('foo', 'vendor/package', '1.1', function (Options $options, $value) {
                        if ($value instanceof \stdClass) {
                            return sprintf('Passing an instance of "%s" to option "foo" is deprecated, pass its FQCN instead.', \stdClass::class);
                        }

                        return '';
                    })
                ;
            },
            ['foo' => new \stdClass()],
            [
                'type' => \E_USER_DEPRECATED,
                'message' => 'Since vendor/package 1.1: Passing an instance of "stdClass" to option "foo" is deprecated, pass its FQCN instead.',
            ],
            1,
        ];

        yield 'It triggers a deprecation based on the value only if option is provided by the user' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined('foo')
                    ->setAllowedTypes('foo', ['null', 'bool'])
                    ->setDeprecated('foo', 'vendor/package', '1.1', function (Options $options, $value) {
                        if (!\is_bool($value)) {
                            return 'Passing a value different than true or false is deprecated.';
                        }

                        return '';
                    })
                    ->setDefault('baz', null)
                    ->setAllowedTypes('baz', ['null', 'int'])
                    ->setDeprecated('baz', 'vendor/package', '1.1', function (Options $options, $value) {
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
                'type' => \E_USER_DEPRECATED,
                'message' => 'Since vendor/package 1.1: Passing a value different than true or false is deprecated.',
            ],
            1,
        ];

        yield 'It ignores a deprecation if closure returns an empty string' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefault('foo', null)
                    ->setDeprecated('foo', 'vendor/package', '1.1', function (Options $options, $value) {
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
                    ->setDeprecated('date_format', 'vendor/package', '1.1', function (Options $options, $dateFormat) {
                        if (null !== $dateFormat && 'single_text' === $options['widget']) {
                            return 'Using the "date_format" option when the "widget" option is set to "single_text" is deprecated.';
                        }

                        return '';
                    })
                ;
            },
            ['widget' => 'single_text', 'date_format' => 2],
            [
                'type' => \E_USER_DEPRECATED,
                'message' => 'Since vendor/package 1.1: Using the "date_format" option when the "widget" option is set to "single_text" is deprecated.',
            ],
            1,
        ];

        yield 'It triggers a deprecation for each evaluation' => [
            function (OptionsResolver $resolver) {
                $resolver
                    // defined by superclass
                    ->setDefined('foo')
                    ->setDeprecated('foo', 'vendor/package', '1.1', 'The option "%name%" is deprecated.')
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
                'type' => \E_USER_DEPRECATED,
                'message' => 'Since vendor/package 1.1: The option "foo" is deprecated.',
            ],
            4,
        ];

        yield 'It ignores a deprecation if no option is provided by the user' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefined('foo')
                    ->setDefault('bar', null)
                    ->setDeprecated('foo', 'vendor/package', '1.1', 'The option "%name%" is deprecated.')
                    ->setDeprecated('bar', 'vendor/package', '1.1', 'The option "%name%" is deprecated.')
                ;
            },
            [],
            null,
            0,
        ];

        yield 'It explicitly ignores a deprecation' => [
            function (OptionsResolver $resolver) {
                $resolver
                    ->setDefault('baz', function (Options $options) {
                        return $options->offsetGet('foo', false);
                    })
                    ->setDefault('foo', null)
                    ->setDeprecated('foo', 'vendor/package', '1.1', 'The option "%name%" is deprecated.')
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

    public function testSetAllowedTypesFailsIfUnknownOption()
    {
        self::expectException(UndefinedOptionsException::class);
        $this->resolver->setAllowedTypes('foo', 'string');
    }

    public function testResolveTypedArray()
    {
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[]');
        $options = $this->resolver->resolve(['foo' => ['bar', 'baz']]);

        self::assertSame(['foo' => ['bar', 'baz']], $options);
    }

    public function testFailIfSetAllowedTypesFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidTypedArray()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "int[]", but one of the elements is of type "DateTime".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(['foo' => [new \DateTime()]]);
    }

    public function testResolveFailsWithNonArray()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value "bar" is expected to be of type "int[]", but is of type "string".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');

        $this->resolver->resolve(['foo' => 'bar']);
    }

    public function testResolveFailsIfTypedArrayContainsInvalidTypes()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "int[]", but one of the elements is of type "stdClass|array|DateTime".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[]');
        $values = range(1, 5);
        $values[] = new \stdClass();
        $values[] = [];
        $values[] = new \DateTime();
        $values[] = 123;

        $this->resolver->resolve(['foo' => $values]);
    }

    public function testResolveFailsWithCorrectLevelsButWrongScalar()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "float".');
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

        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage($exceptionMessage);

        $this->resolver->resolve(['option' => $actualType]);
    }

    public function provideInvalidTypes()
    {
        return [
            [true, 'string', 'The option "option" with value true is expected to be of type "string", but is of type "bool".'],
            [false, 'string', 'The option "option" with value false is expected to be of type "string", but is of type "bool".'],
            [fopen(__FILE__, 'r'), 'string', 'The option "option" with value resource is expected to be of type "string", but is of type "resource (stream)".'],
            [[], 'string', 'The option "option" with value array is expected to be of type "string", but is of type "array".'],
            [new OptionsResolver(), 'string', 'The option "option" with value Symfony\Component\OptionsResolver\OptionsResolver is expected to be of type "string", but is of type "Symfony\Component\OptionsResolver\OptionsResolver".'],
            [42, 'string', 'The option "option" with value 42 is expected to be of type "string", but is of type "int".'],
            [null, 'string', 'The option "option" with value null is expected to be of type "string", but is of type "null".'],
            ['bar', '\stdClass', 'The option "option" with value "bar" is expected to be of type "\stdClass", but is of type "string".'],
            [['foo', 12], 'string[]', 'The option "option" with value array is expected to be of type "string[]", but one of the elements is of type "int".'],
            [123, ['string[]', 'string'], 'The option "option" with value 123 is expected to be of type "string[]" or "string", but is of type "int".'],
            [[null], ['string[]', 'string'], 'The option "option" with value array is expected to be of type "string[]" or "string", but one of the elements is of type "null".'],
            [['string', null], ['string[]', 'string'], 'The option "option" with value array is expected to be of type "string[]" or "string", but one of the elements is of type "null".'],
            [[\stdClass::class], ['string'], 'The option "option" with value array is expected to be of type "string", but is of type "array".'],
        ];
    }

    public function testResolveSucceedsIfValidType()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');

        self::assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidTypeMultiple()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value 42 is expected to be of type "string" or "bool", but is of type "int".');
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedTypes('foo', ['string', 'bool']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidTypeMultiple()
    {
        $this->resolver->setDefault('foo', true);
        $this->resolver->setAllowedTypes('foo', ['string', 'bool']);

        self::assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveSucceedsIfInstanceOfClass()
    {
        $this->resolver->setDefault('foo', new \stdClass());
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        self::assertNotEmpty($this->resolver->resolve());
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
        self::assertEquals($data, $result);
    }

    public function testResolveFailsIfNotInstanceOfClass()
    {
        self::expectException(InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', '\stdClass');

        $this->resolver->resolve();
    }

    public function testAddAllowedTypesFailsIfUnknownOption()
    {
        self::expectException(UndefinedOptionsException::class);
        $this->resolver->addAllowedTypes('foo', 'string');
    }

    public function testFailIfAddAllowedTypesFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->addAllowedTypes('bar', 'string');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidAddedType()
    {
        self::expectException(InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', 'string');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedType()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', 'string');

        self::assertNotEmpty($this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidAddedTypeMultiple()
    {
        self::expectException(InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedTypes('foo', ['string', 'bool']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedTypeMultiple()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedTypes('foo', ['string', 'bool']);

        self::assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', 'bar');

        self::assertNotEmpty($this->resolver->resolve());
    }

    public function testAddAllowedTypesDoesNotOverwrite2()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'string');
        $this->resolver->addAllowedTypes('foo', 'bool');

        $this->resolver->setDefault('foo', false);

        self::assertNotEmpty($this->resolver->resolve());
    }

    public function testSetAllowedValuesFailsIfUnknownOption()
    {
        self::expectException(UndefinedOptionsException::class);
        $this->resolver->setAllowedValues('foo', 'bar');
    }

    public function testFailIfSetAllowedValuesFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidValue()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value 42 is invalid. Accepted values are: "bar".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve(['foo' => 42]);
    }

    public function testResolveFailsIfInvalidValueIsNull()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value null is invalid. Accepted values are: "bar".');
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidValueStrict()
    {
        self::expectException(InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', '42');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValue()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidValueIsNull()
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->setAllowedValues('foo', null);

        self::assertEquals(['foo' => null], $this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidValueMultiple()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value 42 is invalid. Accepted values are: "bar", false, null.');
        $this->resolver->setDefault('foo', 42);
        $this->resolver->setAllowedValues('foo', ['bar', false, null]);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidValueMultiple()
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', ['bar', 'baz']);

        self::assertEquals(['foo' => 'baz'], $this->resolver->resolve());
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
            self::fail('Should fail');
        } catch (InvalidOptionsException $e) {
        }

        self::assertSame(42, $passedValue);
    }

    public function testResolveSucceedsIfClosureReturnsTrue()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function ($value) use (&$passedValue) {
            $passedValue = $value;

            return true;
        });

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
        self::assertSame('bar', $passedValue);
    }

    public function testResolveFailsIfAllClosuresReturnFalse()
    {
        self::expectException(InvalidOptionsException::class);
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

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesFailsIfUnknownOption()
    {
        self::expectException(UndefinedOptionsException::class);
        $this->resolver->addAllowedValues('foo', 'bar');
    }

    public function testFailIfAddAllowedValuesFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->addAllowedValues('bar', 'baz');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testResolveFailsIfInvalidAddedValue()
    {
        self::expectException(InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', 'bar');

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValue()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'bar');

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfValidAddedValueIsNull()
    {
        $this->resolver->setDefault('foo', null);
        $this->resolver->addAllowedValues('foo', null);

        self::assertEquals(['foo' => null], $this->resolver->resolve());
    }

    public function testResolveFailsIfInvalidAddedValueMultiple()
    {
        self::expectException(InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 42);
        $this->resolver->addAllowedValues('foo', ['bar', 'baz']);

        $this->resolver->resolve();
    }

    public function testResolveSucceedsIfValidAddedValueMultiple()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addAllowedValues('foo', ['bar', 'baz']);

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testAddAllowedValuesDoesNotOverwrite2()
    {
        $this->resolver->setDefault('foo', 'baz');
        $this->resolver->setAllowedValues('foo', 'bar');
        $this->resolver->addAllowedValues('foo', 'baz');

        self::assertEquals(['foo' => 'baz'], $this->resolver->resolve());
    }

    public function testResolveFailsIfAllAddedClosuresReturnFalse()
    {
        self::expectException(InvalidOptionsException::class);
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

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testResolveSucceedsIfAnyAddedClosureReturnsTrue2()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', function () { return true; });
        $this->resolver->addAllowedValues('foo', function () { return false; });

        self::assertEquals(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testSetNormalizerReturnsThis()
    {
        $this->resolver->setDefault('foo', 'bar');
        self::assertSame($this->resolver, $this->resolver->setNormalizer('foo', function () {}));
    }

    public function testSetNormalizerClosure()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function () {
            return 'normalized';
        });

        self::assertEquals(['foo' => 'normalized'], $this->resolver->resolve());
    }

    public function testSetNormalizerFailsIfUnknownOption()
    {
        self::expectException(UndefinedOptionsException::class);
        $this->resolver->setNormalizer('foo', function () {});
    }

    public function testFailIfSetNormalizerFromLazyOption()
    {
        self::expectException(AccessException::class);
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

        self::assertEquals(['foo' => 'normalized[bar]'], $this->resolver->resolve());
    }

    public function testNormalizerReceivesPassedOption()
    {
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized['.$value.']';
        });

        $resolved = $this->resolver->resolve(['foo' => 'baz']);

        self::assertEquals(['foo' => 'normalized[baz]'], $resolved);
    }

    public function testValidateTypeBeforeNormalization()
    {
        self::expectException(InvalidOptionsException::class);
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setAllowedTypes('foo', 'int');

        $this->resolver->setNormalizer('foo', function () {
            Assert::fail('Should not be called.');
        });

        $this->resolver->resolve();
    }

    public function testValidateValueBeforeNormalization()
    {
        self::expectException(InvalidOptionsException::class);
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

        self::assertEquals([
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

        self::assertEquals([
            'lazy' => 'bar',
            'norm' => 'normalized',
        ], $this->resolver->resolve());
    }

    public function testFailIfCyclicDependencyBetweenNormalizers()
    {
        self::expectException(OptionDefinitionException::class);
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

    public function testFailIfCyclicDependencyBetweenNormalizerAndLazyOption()
    {
        self::expectException(OptionDefinitionException::class);
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

        self::assertSame(['catcher' => false, 'thrower' => true], $this->resolver->resolve());
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

        self::assertSame(['catcher' => false, 'thrower' => true], $this->resolver->resolve());
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

        self::assertSame(2, $calls);
    }

    public function testNormalizerNotCalledForUnsetOptions()
    {
        $this->resolver->setDefined('norm');

        $this->resolver->setNormalizer('norm', function () {
            Assert::fail('Should not be called.');
        });

        self::assertEmpty($this->resolver->resolve());
    }

    public function testAddNormalizerReturnsThis()
    {
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame($this->resolver, $this->resolver->addNormalizer('foo', function () {}));
    }

    public function testAddNormalizerClosure()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return '1st-normalized-'.$value;
        });
        // defined by subclass
        $this->resolver->addNormalizer('foo', function (Options $options, $value) {
            return '2nd-normalized-'.$value;
        });

        self::assertEquals(['foo' => '2nd-normalized-1st-normalized-bar'], $this->resolver->resolve());
    }

    public function testForcePrependNormalizerClosure()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return '2nd-normalized-'.$value;
        });
        // defined by subclass
        $this->resolver->addNormalizer('foo', function (Options $options, $value) {
            return '1st-normalized-'.$value;
        }, true);

        self::assertEquals(['foo' => '2nd-normalized-1st-normalized-bar'], $this->resolver->resolve());
    }

    public function testForcePrependNormalizerForResolverWithoutPreviousNormalizers()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->addNormalizer('foo', function (Options $options, $value) {
            return '1st-normalized-'.$value;
        }, true);

        self::assertEquals(['foo' => '1st-normalized-bar'], $this->resolver->resolve());
    }

    public function testAddNormalizerFailsIfUnknownOption()
    {
        self::expectException(UndefinedOptionsException::class);
        $this->resolver->addNormalizer('foo', function () {});
    }

    public function testFailIfAddNormalizerFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->addNormalizer('foo', function () {});
        });

        $this->resolver->resolve();
    }

    public function testSetDefaultsReturnsThis()
    {
        self::assertSame($this->resolver, $this->resolver->setDefaults(['foo', 'bar']));
    }

    public function testSetDefaults()
    {
        $this->resolver->setDefault('one', '1');
        $this->resolver->setDefault('two', 'bar');

        $this->resolver->setDefaults([
            'two' => '2',
            'three' => '3',
        ]);

        self::assertEquals([
            'one' => '1',
            'two' => '2',
            'three' => '3',
        ], $this->resolver->resolve());
    }

    public function testFailIfSetDefaultsFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->setDefaults(['two' => '2']);
        });

        $this->resolver->resolve();
    }

    public function testRemoveReturnsThis()
    {
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame($this->resolver, $this->resolver->remove('foo'));
    }

    public function testRemoveSingleOption()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->remove('foo');

        self::assertSame(['baz' => 'boo'], $this->resolver->resolve());
    }

    public function testRemoveMultipleOptions()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setDefault('baz', 'boo');
        $this->resolver->setDefault('doo', 'dam');

        $this->resolver->remove(['foo', 'doo']);

        self::assertSame(['baz' => 'boo'], $this->resolver->resolve());
    }

    public function testRemoveLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });
        $this->resolver->remove('foo');

        self::assertSame([], $this->resolver->resolve());
    }

    public function testRemoveNormalizer()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testRemoveAllowedTypes()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testRemoveAllowedValues()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', ['baz', 'boo']);
        $this->resolver->remove('foo');
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testFailIfRemoveFromLazyOption()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('foo', function (Options $options) {
            $options->remove('bar');
        });

        $this->resolver->setDefault('bar', 'baz');

        $this->resolver->resolve();
    }

    public function testRemoveUnknownOptionIgnored()
    {
        self::assertNotNull($this->resolver->remove('foo'));
    }

    public function testClearReturnsThis()
    {
        self::assertSame($this->resolver, $this->resolver->clear());
    }

    public function testClearRemovesAllOptions()
    {
        $this->resolver->setDefault('one', 1);
        $this->resolver->setDefault('two', 2);

        $this->resolver->clear();

        self::assertEmpty($this->resolver->resolve());
    }

    public function testClearLazyOption()
    {
        $this->resolver->setDefault('foo', function (Options $options) {
            return 'lazy';
        });
        $this->resolver->clear();

        self::assertSame([], $this->resolver->resolve());
    }

    public function testClearNormalizer()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setNormalizer('foo', function (Options $options, $value) {
            return 'normalized';
        });
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testClearAllowedTypes()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedTypes('foo', 'int');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testClearAllowedValues()
    {
        $this->resolver->setDefault('foo', 'bar');
        $this->resolver->setAllowedValues('foo', 'baz');
        $this->resolver->clear();
        $this->resolver->setDefault('foo', 'bar');

        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
    }

    public function testFailIfClearFromLazyption()
    {
        self::expectException(AccessException::class);
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
        self::assertEmpty($this->resolver->resolve());
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

    public function testArrayAccessGetFailsOutsideResolve()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('default', 0);

        $this->resolver['default'];
    }

    public function testArrayAccessExistsFailsOutsideResolve()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('default', 0);

        isset($this->resolver['default']);
    }

    public function testArrayAccessSetNotSupported()
    {
        self::expectException(AccessException::class);
        $this->resolver['default'] = 0;
    }

    public function testArrayAccessUnsetNotSupported()
    {
        self::expectException(AccessException::class);
        $this->resolver->setDefault('default', 0);

        unset($this->resolver['default']);
    }

    public function testFailIfGetNonExisting()
    {
        self::expectException(NoSuchOptionException::class);
        self::expectExceptionMessage('The option "undefined" does not exist. Defined options are: "foo", "lazy".');
        $this->resolver->setDefault('foo', 'bar');

        $this->resolver->setDefault('lazy', function (Options $options) {
            $options['undefined'];
        });

        $this->resolver->resolve();
    }

    public function testFailIfGetDefinedButUnset()
    {
        self::expectException(NoSuchOptionException::class);
        self::expectExceptionMessage('The optional option "defined" has no value set. You should make sure it is set with "isset" before reading it.');
        $this->resolver->setDefined('defined');

        $this->resolver->setDefault('lazy', function (Options $options) {
            $options['defined'];
        });

        $this->resolver->resolve();
    }

    public function testFailIfCyclicDependency()
    {
        self::expectException(OptionDefinitionException::class);
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

        self::assertCount(4, $this->resolver->resolve(['required' => 'value']));
    }

    /**
     * In resolve() we count the options that are actually set (which may be
     * only a subset of the defined options). Outside of resolve(), it's not
     * clear what is counted.
     */
    public function testCountFailsOutsideResolve()
    {
        self::expectException(AccessException::class);
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

        self::assertEquals([
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

        self::assertEquals([
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

    public function testNestedArraysException()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "float[][][][]", but one of the elements is of type "int".');
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

    public function testNestedArrayException1()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "bool|string|array".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');
        $this->resolver->resolve([
            'foo' => [
                [1, true, 'str', [2, 3]],
            ],
        ]);
    }

    public function testNestedArrayException2()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "int[][]", but one of the elements is of type "bool|string|array".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'int[][]');
        $this->resolver->resolve([
            'foo' => [
                [true, 'str', [2, 3]],
            ],
        ]);
    }

    public function testNestedArrayException3()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "string[][][]", but one of the elements is of type "string|int".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[][][]');
        $this->resolver->resolve([
            'foo' => [
                ['str', [1, 2]],
            ],
        ]);
    }

    public function testNestedArrayException4()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "string[][][]", but one of the elements is of type "int".');
        $this->resolver->setDefined('foo');
        $this->resolver->setAllowedTypes('foo', 'string[][][]');
        $this->resolver->resolve([
            'foo' => [
                [
                    ['str'], [1, 2], ],
            ],
        ]);
    }

    public function testNestedArrayException5()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "foo" with value array is expected to be of type "string[]", but one of the elements is of type "array".');
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
        self::assertTrue($this->resolver->isNested('database'));
    }

    public function testFailsIfUndefinedNestedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "database[foo]" does not exist. Defined options are: "host", "port".');
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

    public function testFailsIfMissingRequiredNestedOption()
    {
        self::expectException(MissingOptionsException::class);
        self::expectExceptionMessage('The required option "database[host]" is missing.');
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

    public function testFailsIfInvalidTypeNestedOption()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "database[logging]" with value null is expected to be of type "bool", but is of type "null".');
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

    public function testFailsIfNotArrayIsGivenForNestedOptions()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The nested option "database" with value null is expected to be of type array, but is of type "null".');
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
        self::assertSame($expectedOptions, $actualOptions);
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
        self::assertSame($expectedOptions, $actualOptions);
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
        self::assertSame($expectedOptions, $actualOptions);
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
        self::assertSame($expectedOptions, $actualOptions);
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
        self::assertSame($expectedOptions, $actualOptions);
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
        self::assertSame(['foo' => 'lazy'], $this->resolver->resolve());
    }

    public function testOverwrittenNestedOptionNotEvaluatedIfScalarDefault()
    {
        // defined by superclass
        $this->resolver->setDefault('foo', function (OptionsResolver $resolver) {
            Assert::fail('Should not be called');
        });
        // defined by subclass
        $this->resolver->setDefault('foo', 'bar');
        self::assertSame(['foo' => 'bar'], $this->resolver->resolve());
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
        self::assertSame(['foo' => ['bar' => 'baz']], $this->resolver->resolve());
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
        self::assertSame(['foo' => ['ping' => 'pong', 'bar' => 'baz']], $this->resolver->resolve());
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
        self::assertSame(['foo' => ['bar' => 'baz']], $this->resolver->resolve());
    }

    public function testFailsIfCyclicDependencyBetweenSameNestedOption()
    {
        self::expectException(OptionDefinitionException::class);
        $this->resolver->setDefault('database', function (OptionsResolver $resolver, Options $parent) {
            $resolver->setDefault('replicas', $parent['database']);
        });
        $this->resolver->resolve();
    }

    public function testFailsIfCyclicDependencyBetweenNestedOptionAndParentLazyOption()
    {
        self::expectException(OptionDefinitionException::class);
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

    public function testFailsIfCyclicDependencyBetweenNormalizerAndNestedOption()
    {
        self::expectException(OptionDefinitionException::class);
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

    public function testFailsIfCyclicDependencyBetweenNestedOptions()
    {
        self::expectException(OptionDefinitionException::class);
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
        self::assertSame(['version' => 3.15, 'database' => ['server_version' => 3.15]], $this->resolver->resolve());
    }

    public function testNestedClosureWithoutTypeHintNotInvoked()
    {
        $closure = function ($resolver) {
            Assert::fail('Should not be called');
        };
        $this->resolver->setDefault('foo', $closure);
        self::assertSame(['foo' => $closure], $this->resolver->resolve());
    }

    public function testNestedClosureWithoutTypeHint2ndArgumentNotInvoked()
    {
        $closure = function (OptionsResolver $resolver, $parent) {
            Assert::fail('Should not be called');
        };
        $this->resolver->setDefault('foo', $closure);
        self::assertSame(['foo' => $closure], $this->resolver->resolve());
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
        self::assertSame($expectedOptions, $actualOptions);
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
        self::assertSame($expectedOptions, $actualOptions);
    }

    public function testFailsIfOptionIsAlreadyDefined()
    {
        self::expectException(OptionDefinitionException::class);
        self::expectExceptionMessage('The option "foo" is already defined.');
        $this->resolver->define('foo');
        $this->resolver->define('foo');
    }

    public function testResolveOptionsDefinedByOptionConfigurator()
    {
        $this->resolver->define('foo')
            ->required()
            ->deprecated('vendor/package', '1.1')
            ->default('bar')
            ->allowedTypes('string', 'bool')
            ->allowedValues('bar', 'zab')
            ->normalize(static function (Options $options, $value) {
                return $value;
            })
            ->info('info message')
        ;
        $introspector = new OptionsResolverIntrospector($this->resolver);

        self::assertTrue(true, $this->resolver->isDefined('foo'));
        self::assertTrue(true, $this->resolver->isDeprecated('foo'));
        self::assertTrue(true, $this->resolver->hasDefault('foo'));
        self::assertSame('bar', $introspector->getDefault('foo'));
        self::assertSame(['string', 'bool'], $introspector->getAllowedTypes('foo'));
        self::assertSame(['bar', 'zab'], $introspector->getAllowedValues('foo'));
        self::assertCount(1, $introspector->getNormalizers('foo'));
        self::assertSame('info message', $this->resolver->getInfo('foo'));
    }

    public function testGetInfo()
    {
        $info = 'The option info message';
        $this->resolver->setDefined('foo');
        $this->resolver->setInfo('foo', $info);

        self::assertSame($info, $this->resolver->getInfo('foo'));
    }

    public function testSetInfoOnNormalization()
    {
        self::expectException(AccessException::class);
        self::expectExceptionMessage('The Info message cannot be set from a lazy option or normalizer.');

        $this->resolver->setDefined('foo');
        $this->resolver->setNormalizer('foo', static function (Options $options, $value) {
            $options->setInfo('foo', 'Info');
        });

        $this->resolver->resolve(['foo' => 'bar']);
    }

    public function testSetInfoOnUndefinedOption()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "bar" does not exist. Defined options are: "foo".');

        $this->resolver->setDefined('foo');
        $this->resolver->setInfo('bar', 'The option info message');
    }

    public function testGetInfoOnUndefinedOption2()
    {
        self::expectException(UndefinedOptionsException::class);
        self::expectExceptionMessage('The option "bar" does not exist. Defined options are: "foo".');

        $this->resolver->setDefined('foo');
        $this->resolver->getInfo('bar');
    }

    public function testInfoOnInvalidValue()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The option "expires" with value DateTime is invalid. Info: A future date time.');

        $this->resolver
            ->setRequired('expires')
            ->setInfo('expires', 'A future date time')
            ->setAllowedTypes('expires', \DateTime::class)
            ->setAllowedValues('expires', static function ($value) {
                return $value >= new \DateTime('now');
            })
        ;

        $this->resolver->resolve(['expires' => new \DateTime('-1 hour')]);
    }

    /**
     * @group legacy
     */
    public function testSetDeprecatedWithoutPackageAndVersion()
    {
        $this->expectDeprecation('Since symfony/options-resolver 5.1: The signature of method "Symfony\Component\OptionsResolver\OptionsResolver::setDeprecated()" requires 2 new arguments: "string $package, string $version", not defining them is deprecated.');

        $this->resolver
            ->setDefined('foo')
            ->setDeprecated('foo')
        ;
    }

    public function testInvalidValueForPrototypeDefinition()
    {
        self::expectException(InvalidOptionsException::class);
        self::expectExceptionMessage('The value of the option "connections" is expected to be of type array of array, but is of type array of "string".');

        $this->resolver
            ->setDefault('connections', static function (OptionsResolver $resolver) {
                $resolver
                    ->setPrototype(true)
                    ->setDefined(['table', 'user', 'password'])
                ;
            })
        ;

        $this->resolver->resolve(['connections' => ['foo']]);
    }

    public function testMissingOptionForPrototypeDefinition()
    {
        self::expectException(MissingOptionsException::class);
        self::expectExceptionMessage('The required option "connections[1][table]" is missing.');

        $this->resolver
            ->setDefault('connections', static function (OptionsResolver $resolver) {
                $resolver
                    ->setPrototype(true)
                    ->setRequired('table')
                ;
            })
        ;

        $this->resolver->resolve(['connections' => [
            ['table' => 'default'],
            [], // <- missing required option "table"
        ]]);
    }

    public function testAccessExceptionOnPrototypeDefinition()
    {
        self::expectException(AccessException::class);
        self::expectExceptionMessage('The prototype property cannot be set from a root definition.');

        $this->resolver->setPrototype(true);
    }

    public function testPrototypeDefinition()
    {
        $this->resolver
            ->setDefault('connections', static function (OptionsResolver $resolver) {
                $resolver
                    ->setPrototype(true)
                    ->setRequired('table')
                    ->setDefaults(['user' => 'root', 'password' => null])
                ;
            })
        ;

        $actualOptions = $this->resolver->resolve([
            'connections' => [
                'default' => [
                    'table' => 'default',
                ],
                'custom' => [
                    'user' => 'foo',
                    'password' => 'pa$$',
                    'table' => 'symfony',
                ],
            ],
        ]);
        $expectedOptions = [
            'connections' => [
                'default' => [
                    'user' => 'root',
                    'password' => null,
                    'table' => 'default',
                ],
                'custom' => [
                    'user' => 'foo',
                    'password' => 'pa$$',
                    'table' => 'symfony',
                ],
            ],
        ];

        self::assertSame($expectedOptions, $actualOptions);
    }
}
