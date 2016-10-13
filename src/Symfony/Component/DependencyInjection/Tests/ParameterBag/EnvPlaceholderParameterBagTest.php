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
}
