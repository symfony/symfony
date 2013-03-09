<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition;

use Symfony\Component\Config\Definition\ReferenceDumper;
use Symfony\Component\Config\Tests\Fixtures\Configuration\ExampleConfiguration;

class ReferenceDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDumper()
    {
        $configuration = new ExampleConfiguration();

        $dumper = new ReferenceDumper();
        $this->assertEquals($this->getConfigurationAsString(), $dumper->dump($configuration));
    }

    private function getConfigurationAsString()
    {
      return <<<EOL
root:
    boolean:              true
    scalar_empty:         ~
    scalar_null:          ~
    scalar_true:          true
    scalar_false:         false
    scalar_default:       default
    scalar_array_empty:   []
    scalar_array_defaults:

        # Defaults:
        - elem1
        - elem2

    # some info
    array:
        child1:               ~
        child2:               ~

        # this is a long
        # multi-line info text
        # which should be indented
        child3:               ~ # Example: example setting
    array_prototype:
        parameters:

            # Prototype
            name:
                value:                ~ # Required

EOL;
    }
}
