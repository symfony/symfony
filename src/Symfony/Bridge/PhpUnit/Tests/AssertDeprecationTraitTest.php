<?php

namespace Symfony\Bridge\PhpUnit\Tests;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\AssertDeprecationTrait;

final class AssertDeprecationTraitTest extends TestCase
{
    public function testExpectedDeprecationHasBeenRaised()
    {
        $test = new class() {
            use AssertDeprecationTrait;

            public function run(): string
            {
                return self::assertDeprecation(
                    'Since foo/bar 47.11: Stop using this.',
                    static function (): string {
                        trigger_deprecation('foo/bar', '47.11', 'Stop using this.');

                        return 'foo';
                    }
                );
            }
        };

        self::assertSame('foo', $test->run());
    }

    public function testExpectedDeprecationHasBeenRaisedAmongOthers()
    {
        $test = new class() {
            use AssertDeprecationTrait;

            public function run(): string
            {
                return self::assertDeprecation(
                    'Since foo/bar 47.11: Stop using this.',
                    static function (): string {
                        trigger_deprecation('fuz/baz', '0.8.15', 'Ignore me.');
                        trigger_deprecation('foo/bar', '47.11', 'Stop using this.');

                        return 'foo';
                    }
                );
            }
        };

        $loggedDeprecations = [];
        $previous = null;
        $previous = set_error_handler(static function ($errno, $errstr) use (&$loggedDeprecations, &$previous) {
            if ($errno === E_USER_DEPRECATED) {
                $loggedDeprecations[] = $errstr;

                return true;
            }

            return $previous(...func_get_args());
        }) ?? static function () {
            return false;
        };

        try {
            self::assertSame('foo', $test->run());
        } finally {
            restore_error_handler();
        }

        self::assertSame(['Since fuz/baz 0.8.15: Ignore me.'], $loggedDeprecations);
    }

    public function testNoDeprecationHasBeenRaised()
    {
        $test = new class() {
            use AssertDeprecationTrait;

            public function run(): string
            {
                return self::assertDeprecation(
                    'Since foo/bar 47.11: Stop using this.',
                    static function (): void {
                    }
                );
            }
        };

        $e = null;
        try {
            $test->run();
        } catch (ExpectationFailedException $e) {
        }

        self::assertNotNull($e);
        self::assertSame(
            "The following deprecation has not been raised: Since foo/bar 47.11: Stop using this.\nNo other deprecations have been observed.\nFailed asserting that false is true.",
            $e->getMessage()
        );
    }

    public function testOtherDeprecationsHaveBeenRaised()
    {
        $test = new class() {
            use AssertDeprecationTrait;

            public function run(): string
            {
                return self::assertDeprecation(
                    'Since foo/bar 47.11: Stop using this.',
                    static function (): void {
                        trigger_deprecation('fuz/baz', '0.8.15', 'Ignore me.');
                        trigger_deprecation('fiz/buz', '0.8.16', 'And me as well.');
                    }
                );
            }
        };

        $e = null;
        $loggedDeprecations = [];
        $previous = null;
        $previous = set_error_handler(static function ($errno, $errstr) use (&$loggedDeprecations, &$previous) {
            if ($errno === E_USER_DEPRECATED) {
                $loggedDeprecations[] = $errstr;

                return true;
            }

            return $previous(...func_get_args());
        }) ?? static function () {
            return false;
        };
        try {
            $test->run();
        } catch (ExpectationFailedException $e) {
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($e);
        self::assertSame(
            "The following deprecation has not been raised: Since foo/bar 47.11: Stop using this.\nInstead, the following deprecations have been observed:\n - Since fuz/baz 0.8.15: Ignore me.\n - Since fiz/buz 0.8.16: And me as well.\nFailed asserting that false is true.",
            $e->getMessage()
        );
        self::assertSame([
            'Since fuz/baz 0.8.15: Ignore me.',
            'Since fiz/buz 0.8.16: And me as well.',
        ], $loggedDeprecations);
    }
}
