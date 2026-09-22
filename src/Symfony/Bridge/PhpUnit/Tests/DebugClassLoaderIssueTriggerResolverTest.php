<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpunit;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DebugClassLoaderIssueTriggerResolver;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\Caller;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\ExtendsFinalParent;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\FinalParent;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\TriggeringClass;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\TriggeringInterface;
use Symfony\Bridge\PhpUnit\Tests\Fixtures\IssueTriggerResolver\TriggeringTrait;
use Symfony\Component\ErrorHandler\DebugClassLoader;

/**
 * @requires PHPUnit >= 13.1
 */
#[RequiresPhpunit('>=13.1.0')]
final class DebugClassLoaderIssueTriggerResolverTest extends TestCase
{
    #[DataProvider('provideDebugClassLoaderDeprecations')]
    public function testResolvesDebugClassLoaderDeprecations(string $message, string $triggeringClass)
    {
        class_exists(Caller::class);

        if (!class_exists($triggeringClass) && !interface_exists($triggeringClass) && !trait_exists($triggeringClass)) {
            $this->fail(\sprintf('The triggering class "%s" could not be loaded.', $triggeringClass));
        }

        $resolution = (new DebugClassLoaderIssueTriggerResolver())->resolve($this->trace($message), $message);

        $this->assertNotNull($resolution);
        $this->assertSame((new \ReflectionClass($triggeringClass))->getFileName(), $resolution->callee());
        $this->assertSame((new \ReflectionClass(Caller::class))->getFileName(), $resolution->caller());
    }

    public static function provideDebugClassLoaderDeprecations(): iterable
    {
        yield 'final class' => [
            \sprintf('The "%s" class is considered final. It may change without further notice as of its next major version. You should not extend it from "%s".', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];

        yield 'deprecated class' => [
            \sprintf('The "%s" class extends "%s" that is deprecated.', Caller::class, TriggeringClass::class),
            TriggeringClass::class,
        ];

        yield 'deprecated interface' => [
            \sprintf('The "%s" class implements "%s" that is deprecated.', Caller::class, TriggeringInterface::class),
            TriggeringInterface::class,
        ];

        yield 'deprecated trait' => [
            \sprintf('The "%s" class uses "%s" that is deprecated.', Caller::class, TriggeringTrait::class),
            TriggeringTrait::class,
        ];

        yield 'internal class' => [
            \sprintf('The "%s" class is considered internal. It may change without further notice. You should not use it from "%s".', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];

        yield 'internal interface' => [
            \sprintf('The "%s" interface is considered internal. It may change without further notice. You should not use it from "%s".', TriggeringInterface::class, Caller::class),
            TriggeringInterface::class,
        ];

        yield 'internal trait' => [
            \sprintf('The "%s" trait is considered internal. It may change without further notice. You should not use it from "%s".', TriggeringTrait::class, Caller::class),
            TriggeringTrait::class,
        ];

        yield 'virtual method' => [
            \sprintf('Class "%s" should implement method "%s::method()".', Caller::class, TriggeringInterface::class),
            TriggeringInterface::class,
        ];

        yield 'static virtual method' => [
            \sprintf('Class "%s" should implement method "static %s::method(): string".', Caller::class, TriggeringInterface::class),
            TriggeringInterface::class,
        ];

        yield 'final method' => [
            \sprintf('The "%s::method()" method is considered final. It may change without further notice as of its next major version. You should not extend it from "%s".', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];

        yield 'internal method' => [
            \sprintf('The "%s::method()" method is considered internal. It may change without further notice. You should not extend it from "%s".', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];

        yield 'virtual method-like final description' => [
            \sprintf('The "%s::method()" method is considered final because it should implement method "%s::other()". It may change without further notice as of its next major version. You should not extend it from "%s".', TriggeringClass::class, Caller::class, Caller::class),
            TriggeringClass::class,
        ];

        yield 'final property' => [
            \sprintf('The "%s::$property" property is considered final. You should not override it in "%s".', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];

        yield 'final constant' => [
            \sprintf('The "%s::CONSTANT" constant is considered final. You should not override it in "%s".', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];
    }

    #[DataProvider('provideDeprecationsAboutTheLoadedClass')]
    public function testUsesLoadedClassAsCalleeForDeprecationsAboutThatClass(string $message)
    {
        class_exists(Caller::class);

        $resolution = (new DebugClassLoaderIssueTriggerResolver())->resolve($this->trace($message), $message);

        $this->assertNotNull($resolution);
        $this->assertSame((new \ReflectionClass(Caller::class))->getFileName(), $resolution->callee());
        $this->assertSame((new \ReflectionClass(Caller::class))->getFileName(), $resolution->caller());
    }

    public static function provideDeprecationsAboutTheLoadedClass(): iterable
    {
        yield 'new parameter from parent class' => [
            \sprintf('The "%s::method()" method will require a new "string $value" argument in the next major version of its parent class "%s", not defining it is deprecated.', Caller::class, TriggeringClass::class),
        ];

        yield 'new parameter from interface' => [
            \sprintf('The "%s::method()" method will require a new "string $value" argument in the next major version of its interface "%s", not defining it is deprecated.', Caller::class, TriggeringInterface::class),
        ];

        yield 'unrecognized DebugClassLoader message' => ['A future DebugClassLoader deprecation'];
    }

    #[DataProvider('provideReturnTypeDeprecations')]
    public function testUsesDeclaringClassAsCalleeForReturnTypeDeprecations(string $message, string $declaringClass)
    {
        class_exists(Caller::class);

        if (!class_exists($declaringClass) && !interface_exists($declaringClass)) {
            $this->fail(\sprintf('The declaring class "%s" could not be loaded.', $declaringClass));
        }

        $resolution = (new DebugClassLoaderIssueTriggerResolver())->resolve($this->trace($message), $message);

        $this->assertNotNull($resolution);
        $this->assertSame((new \ReflectionClass($declaringClass))->getFileName(), $resolution->callee());
        $this->assertSame((new \ReflectionClass(Caller::class))->getFileName(), $resolution->caller());
    }

    public static function provideReturnTypeDeprecations(): iterable
    {
        yield 'native return type from parent class' => [
            \sprintf('Method "%s::method()" might add "string" as a native return type declaration in the future. Do the same in child class "%s" now to avoid errors or add an explicit @return annotation to suppress this message.', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];

        yield 'native return type from interface' => [
            \sprintf('Method "%s::method()" might add "string" as a native return type declaration in the future. Do the same in implementation "%s" now to avoid errors or add an explicit @return annotation to suppress this message.', TriggeringInterface::class, Caller::class),
            TriggeringInterface::class,
        ];

        yield 'legacy native return type' => [
            \sprintf('Method "%s::method()" will return "string" as of its next major version. Doing the same in child class "%s" will be required when upgrading.', TriggeringClass::class, Caller::class),
            TriggeringClass::class,
        ];
    }

    public function testResolvesDeprecationTriggeredWhileLoadingAnAnonymousClass()
    {
        class_exists(TriggeringClass::class);
        $caller = new class {
        };
        $message = \sprintf('The "%s" class is considered final. It may change without further notice as of its next major version. You should not extend it from "class@anonymous".', TriggeringClass::class);

        $resolution = (new DebugClassLoaderIssueTriggerResolver())->resolve($this->trace($message, $caller::class), $message);

        $this->assertNotNull($resolution);
        $this->assertSame((new \ReflectionClass(TriggeringClass::class))->getFileName(), $resolution->callee());
        $this->assertSame(__FILE__, $resolution->caller());
    }

    public function testUsesFilePassedToDebugClassLoaderAsCaller()
    {
        class_exists(Caller::class);
        class_exists(TriggeringClass::class);
        $message = \sprintf('The "%s" class is considered final. It may change without further notice as of its next major version. You should not extend it from "%s".', TriggeringClass::class, Caller::class);

        $resolution = (new DebugClassLoaderIssueTriggerResolver())->resolve($this->trace($message, Caller::class, __FILE__), $message);

        $this->assertNotNull($resolution);
        $this->assertSame(__FILE__, $resolution->caller());
    }

    public function testReturnsAResolutionWithoutCalleeFileForInternalClass()
    {
        class_exists(Caller::class);
        $message = \sprintf('The "%s" interface is considered internal. It may change without further notice. You should not use it from "%s".', \IteratorAggregate::class, Caller::class);

        $resolution = (new DebugClassLoaderIssueTriggerResolver())->resolve($this->trace($message), $message);

        $this->assertNotNull($resolution);
        $this->assertFalse($resolution->hasCallee());
        $this->assertSame((new \ReflectionClass(Caller::class))->getFileName(), $resolution->caller());
    }

    public function testUsesInnermostRecursiveDebugClassLoaderFrame()
    {
        class_exists(Caller::class);
        class_exists(TriggeringClass::class);
        $message = \sprintf('The "%s" class is considered final. It may change without further notice as of its next major version. You should not extend it from "%s".', TriggeringClass::class, Caller::class);
        $trace = [
            ['function' => 'trigger_error', 'args' => [$message, \E_USER_DEPRECATED]],
            ['class' => DebugClassLoader::class, 'function' => 'checkClass', 'args' => [Caller::class]],
            ['class' => DebugClassLoader::class, 'function' => 'checkAnnotations'],
            ['class' => DebugClassLoader::class, 'function' => 'checkClass', 'args' => [TriggeringClass::class]],
        ];

        $resolution = (new DebugClassLoaderIssueTriggerResolver())->resolve($trace, $message);

        $this->assertNotNull($resolution);
        $this->assertSame((new \ReflectionClass(Caller::class))->getFileName(), $resolution->caller());
    }

    #[DataProvider('provideUnrelatedOrIncompleteTraces')]
    public function testDoesNotResolveUnrelatedOrIncompleteTraces(array $trace, string $message)
    {
        $this->assertNull((new DebugClassLoaderIssueTriggerResolver())->resolve($trace, $message));
    }

    public static function provideUnrelatedOrIncompleteTraces(): iterable
    {
        $message = \sprintf('The "%s" class is considered final. It may change without further notice as of its next major version. You should not extend it from "%s".', TriggeringClass::class, Caller::class);
        $trigger = [
            'function' => 'trigger_error',
            'args' => [$message, \E_USER_DEPRECATED],
        ];
        $loader = [
            'class' => DebugClassLoader::class,
            'function' => 'checkClass',
            'args' => [Caller::class],
        ];

        yield 'empty trace' => [[], $message];
        yield 'without DebugClassLoader frame' => [[$trigger], $message];
        yield 'without trigger_error frame' => [[$loader], $message];
        yield 'different DebugClassLoader method' => [
            [
                $trigger,
                ['class' => DebugClassLoader::class, 'function' => 'loadClass', 'args' => [Caller::class]],
            ],
            $message,
        ];
        yield 'dependency between trigger and DebugClassLoader' => [[$trigger, ['class' => self::class, 'function' => 'dependency'], $loader], $message];
        yield 'object method named trigger_error' => [[['class' => self::class] + $trigger, $loader], $message];
        yield 'different message' => [[['function' => 'trigger_error', 'args' => ['another message', \E_USER_DEPRECATED]], $loader], $message];
        yield 'different error level' => [[['function' => 'trigger_error', 'args' => [$message, \E_USER_WARNING]], $loader], $message];
        yield 'missing loaded class' => [[$trigger, ['class' => DebugClassLoader::class, 'function' => 'checkClass', 'args' => []]], $message];
        yield 'unknown loaded class' => [[$trigger, ['class' => DebugClassLoader::class, 'function' => 'checkClass', 'args' => ['Missing\\LoadedClass']]], $message];
        yield 'unknown triggering class' => [
            [
                ['function' => 'trigger_error', 'args' => ['The "Missing\\TriggeringClass" class is considered final.', \E_USER_DEPRECATED]],
                $loader,
            ],
            'The "Missing\\TriggeringClass" class is considered final.',
        ];
    }

    public function testResolvesAnActualDebugClassLoaderDeprecation()
    {
        $files = [
            FinalParent::class => __DIR__.'/Fixtures/IssueTriggerResolver/FinalParent.php',
            ExtendsFinalParent::class => __DIR__.'/Fixtures/IssueTriggerResolver/ExtendsFinalParent.php',
        ];
        $loader = new DebugClassLoader(static function (string $class) use ($files): void {
            require $files[$class];
        });
        $resolver = new DebugClassLoaderIssueTriggerResolver();
        $resolution = $message = null;

        set_error_handler(static function (int $type, string $currentMessage) use (&$message, &$resolution, $resolver): bool {
            if (\E_USER_DEPRECATED !== $type) {
                return false;
            }

            $message = $currentMessage;
            $resolution = $resolver->resolve(debug_backtrace(), $currentMessage);

            return true;
        });

        try {
            $loader->loadClass(FinalParent::class);
            $loader->loadClass(ExtendsFinalParent::class);
        } finally {
            restore_error_handler();
        }

        $this->assertNotNull($message);
        $this->assertStringContainsString('class is considered final', $message);
        $this->assertNotNull($resolution);
        $this->assertSame(realpath($files[FinalParent::class]), $resolution->callee());
        $this->assertSame(realpath($files[ExtendsFinalParent::class]), $resolution->caller());
    }

    public function testClassifiesDebugClassLoaderDeprecationAsDirectWhenRegisteredThroughPhpunitConfiguration()
    {
        [$output, $status] = $this->runPhpunit('phpunit-without-resolver.xml.dist');

        $this->assertSame(0, $status, $output);
        $this->assertStringNotContainsString('class is considered final', $output);

        [$output, $status] = $this->runPhpunit('phpunit-with-resolver.xml.dist');

        $this->assertSame(1, $status, $output);
        $this->assertStringContainsString('Deprecations: 1', $output);
        $this->assertStringContainsString('class is considered final', $output);
    }

    public function testFiltersDebugClassLoaderDeprecationAsDirectWhenRegisteredThroughPhpunitConfiguration()
    {
        [$output, $status] = $this->runPhpunit('phpunit-with-resolver-ignoring-direct.xml.dist');

        $this->assertSame(0, $status, $output);
        $this->assertStringNotContainsString('class is considered final', $output);
    }

    private function trace(string $message, string $caller = Caller::class, ?string $file = null): array
    {
        $args = [$caller];

        if (null !== $file) {
            $args[] = $file;
        }

        return [
            [
                'function' => 'trigger_error',
                'args' => [$message, \E_USER_DEPRECATED],
            ],
            [
                'class' => DebugClassLoader::class,
                'function' => 'checkClass',
                'args' => $args,
            ],
        ];
    }

    /**
     * @return array{string, int}
     */
    private function runPhpunit(string $configuration): array
    {
        $command = [
            \PHP_BINARY,
            \dirname(\PHPUNIT_COMPOSER_INSTALL, 2).'/phpunit',
            '--configuration',
            $configuration,
            '--colors=never',
        ];
        $environment = getenv();
        $environment['SYMFONY_DEPRECATIONS_HELPER'] = 'disabled';
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__.'/Fixtures/IssueTriggerResolverIntegration', $environment);

        $this->assertIsResource($process);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $output .= stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [$output, proc_close($process)];
    }
}
