<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\ParameterBag;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Loader\Configurator\EnvConfigurator;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Tests\Fixtures\StringBackedEnum;

class EnvPlaceholderParameterBagTest extends TestCase
{
    public function testEnumEnvVarProcessorPassesRegex()
    {
        $bag = new EnvPlaceholderParameterBag();
        $name = trim((new EnvConfigurator('FOO'))->enum(StringBackedEnum::class), '%');
        $this->assertIsString($bag->get($name));
    }

    public function testGetThrowsInvalidArgumentExceptionIfEnvNameContainsNonWordCharacters()
    {
        $bag = new EnvPlaceholderParameterBag();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The given env var name "env(%foo%)" contains invalid characters (allowed characters: letters, digits, underscores, backslashes and colons).');
        $bag->get('env(%foo%)');
    }

    public function testMergeWillNotDuplicateIdenticalParameters()
    {
        $envVariableName = 'DB_HOST';
        $parameter = sprintf('env(%s)', $envVariableName);
        $firstBag = new EnvPlaceholderParameterBag();

        // initialize placeholders
        $firstBag->get($parameter);
        $secondBag = clone $firstBag;

        $firstBag->mergeEnvPlaceholders($secondBag);
        $mergedPlaceholders = $firstBag->getEnvPlaceholders();

        $placeholderForVariable = $mergedPlaceholders[$envVariableName];
        $placeholder = array_values($placeholderForVariable)[0];

        $this->assertCount(1, $placeholderForVariable);
        $this->assertIsString($placeholder);
        $this->assertStringContainsString($envVariableName, $placeholder);
    }

    public function testMergeWhereFirstBagIsEmptyWillWork()
    {
        $envVariableName = 'DB_HOST';
        $parameter = sprintf('env(%s)', $envVariableName);
        $firstBag = new EnvPlaceholderParameterBag();
        $secondBag = new EnvPlaceholderParameterBag();

        // initialize placeholder only in second bag
        $secondBag->get($parameter);

        $this->assertEmpty($firstBag->getEnvPlaceholders());

        $firstBag->mergeEnvPlaceholders($secondBag);
        $mergedPlaceholders = $firstBag->getEnvPlaceholders();

        $placeholderForVariable = $mergedPlaceholders[$envVariableName];
        $placeholder = array_values($placeholderForVariable)[0];

        $this->assertCount(1, $placeholderForVariable);
        $this->assertIsString($placeholder);
        $this->assertStringContainsString($envVariableName, $placeholder);
    }

    public function testMergeWherePlaceholderOnlyExistsInSecond()
    {
        $uniqueEnvName = 'DB_HOST';
        $commonEnvName = 'DB_USER';

        $uniqueParamName = sprintf('env(%s)', $uniqueEnvName);
        $commonParamName = sprintf('env(%s)', $commonEnvName);

        $firstBag = new EnvPlaceholderParameterBag();
        // initialize common placeholder
        $firstBag->get($commonParamName);
        $secondBag = clone $firstBag;

        // initialize unique placeholder
        $secondBag->get($uniqueParamName);

        $firstBag->mergeEnvPlaceholders($secondBag);
        $merged = $firstBag->getEnvPlaceholders();

        $this->assertCount(1, $merged[$uniqueEnvName]);
        // second bag has same placeholder for commonEnvName
        $this->assertCount(1, $merged[$commonEnvName]);
    }

    public function testMergeWithDifferentIdentifiersForPlaceholders()
    {
        $envName = 'DB_USER';
        $paramName = sprintf('env(%s)', $envName);

        $firstBag = new EnvPlaceholderParameterBag();
        $secondBag = new EnvPlaceholderParameterBag();
        // initialize placeholders
        $firstPlaceholder = $firstBag->get($paramName);
        $secondPlaceholder = $secondBag->get($paramName);

        $firstBag->mergeEnvPlaceholders($secondBag);
        $merged = $firstBag->getEnvPlaceholders();

        $this->assertNotEquals($firstPlaceholder, $secondPlaceholder);
        $this->assertCount(2, $merged[$envName]);
    }

    public function testResolveEnvRequiresStrings()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The default value of env parameter "INT_VAR" must be a string or null, "int" given.');

        $bag = new EnvPlaceholderParameterBag();
        $bag->get('env(INT_VAR)');
        $bag->set('env(INT_VAR)', 2);
        $bag->resolve();
    }

    public function testGetDefaultScalarEnv()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The default value of an env() parameter must be a string or null, but "int" given to "env(INT_VAR)".');

        $bag = new EnvPlaceholderParameterBag();
        $bag->set('env(INT_VAR)', 2);
        $bag->get('env(INT_VAR)');
    }

    public function testGetDefaultEnv()
    {
        $bag = new EnvPlaceholderParameterBag();
        $this->assertStringMatchesFormat('env_%s_INT_VAR_%s', $bag->get('env(INT_VAR)'));
        $bag->set('env(INT_VAR)', '2');
        $this->assertStringMatchesFormat('env_%s_INT_VAR_%s', $bag->get('env(INT_VAR)'));
        $this->assertSame('2', $bag->all()['env(INT_VAR)']);
        $bag->resolve();
        $this->assertStringMatchesFormat('env_%s_INT_VAR_%s', $bag->get('env(INT_VAR)'));
        $this->assertSame('2', $bag->all()['env(INT_VAR)']);
    }

    public function testResolveEnvAllowsNull()
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->get('env(NULL_VAR)');
        $bag->set('env(NULL_VAR)', null);
        $bag->resolve();
        $this->assertNull($bag->all()['env(NULL_VAR)']);
    }

    public function testResolveThrowsOnBadDefaultValue()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The default value of env parameter "ARRAY_VAR" must be a string or null, "array" given.');
        $bag = new EnvPlaceholderParameterBag();
        $bag->get('env(ARRAY_VAR)');
        $bag->set('env(ARRAY_VAR)', []);
        $bag->resolve();
    }

    public function testGetEnvAllowsNull()
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->set('env(NULL_VAR)', null);
        $bag->get('env(NULL_VAR)');
        $bag->resolve();

        $this->assertNull($bag->all()['env(NULL_VAR)']);
    }

    public function testGetThrowsOnBadDefaultValue()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The default value of an env() parameter must be a string or null, but "array" given to "env(ARRAY_VAR)".');
        $bag = new EnvPlaceholderParameterBag();
        $bag->set('env(ARRAY_VAR)', []);
        $bag->get('env(ARRAY_VAR)');
        $bag->resolve();
    }

    public function testDefaultToNullAllowed()
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->resolve();
        $this->assertNotNull($bag->get('env(default::BAR)'));
    }

    public function testExtraCharsInProcessor()
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->resolve();
        $this->assertStringMatchesFormat('env_%s_key_a_b_c_FOO_%s', $bag->get('env(key:a.b-c:FOO)'));
    }

    public function testGetEnum()
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->set('ENUM_VAR', StringBackedEnum::Bar);
        $this->assertInstanceOf(StringBackedEnum::class, $bag->get('ENUM_VAR'));
        $this->assertEquals(StringBackedEnum::Bar, $bag->get('ENUM_VAR'));
    }
}
