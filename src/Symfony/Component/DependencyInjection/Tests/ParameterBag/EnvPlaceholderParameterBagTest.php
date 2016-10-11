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
        $originalPlaceholders = array('database_host' => array('localhost'));
        $firstBag = new EnvPlaceholderParameterBag($originalPlaceholders);

        // initialize placeholders
        $firstBag->get('env(database_host)');
        $secondBag = clone $firstBag;

        $firstBag->mergeEnvPlaceholders($secondBag);
        $mergedPlaceholders = $firstBag->getEnvPlaceholders();

        $this->assertCount(1, $mergedPlaceholders['database_host']);
    }
}
