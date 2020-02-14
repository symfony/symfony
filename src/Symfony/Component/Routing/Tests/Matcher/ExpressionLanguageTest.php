<?php

namespace Symfony\Component\Routing\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Routing\Matcher\ExpressionLanguageProvider;

class ExpressionLanguageTest extends TestCase
{
    public function setUp()
    {
        $_SERVER['test__APP_ENV'] = 'test';
        $_SERVER['test__PHP_VERSION'] = '7.2';
    }

    /**
     * @dataProvider provider
     */
    public function testEnv(string $expression, $expected): void
    {
        $expressionLanguage = new ExpressionLanguage();
        $expressionLanguage->registerProvider(new ExpressionLanguageProvider());

        $this->assertEquals($expected, $expressionLanguage->evaluate($expression));
    }

    public function provider(): array
    {
        return [
            ['env("test__APP_ENV")', 'test'],
            ['env("test__PHP_VERSION")', '7.2'],
            ['env("test__unknown_env_variable")', null],
            ['env("test__unknown_env_variable", "default")', 'default'],
        ];
    }

    public function tearDown()
    {
        unset($_SERVER['test__APP_ENV'], $_SERVER['test__PHP_VERSION']);
    }
}
