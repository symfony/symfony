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

use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

class EnvPlaceholderParameterBagTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testGetThrowsInvalidArgumentExceptionIfEnvNameContainsNonWordCharacters()
    {
        $bag = new EnvPlaceholderParameterBag();
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
        $this->assertInternalType('string', $placeholder);
        $this->assertContains($envVariableName, $placeholder);
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
        $this->assertInternalType('string', $placeholder);
        $this->assertContains($envVariableName, $placeholder);
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

    public function testResolveEnvCastsIntToString()
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->get('env(INT)');
        $bag->set('env(INT)', 2);
        $bag->resolve();
        $this->assertSame('2', $bag->all()['env(int)']);
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage The default value of env parameter "ARRAY" must be string or null, array given.
     */
    public function testResolveThrowsOnBadDefaultValue()
    {
        $bag = new EnvPlaceholderParameterBag();
        $bag->get('env(ARRAY)');
        $bag->set('env(ARRAY)', array());
        $bag->resolve();
    }
}
