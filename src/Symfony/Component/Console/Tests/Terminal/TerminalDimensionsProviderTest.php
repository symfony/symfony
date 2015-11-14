<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Terminal;

use PHPUnit_Framework_TestCase;
use Symfony\Component\Console\Terminal\TerminalDimensionsProvider;
use Symfony\Component\Console\Terminal\TerminalDimensionsProviderInterface;

class TerminalDimensionsProviderTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var TerminalDimensionsProviderInterface
     */
    private $terminalDimensionsProvider;

    protected function setUp()
    {
        $this->terminalDimensionsProvider = new TerminalDimensionsProvider();
    }

    public function testGetTerminalDimensions()
    {
        $dimensions = $this->terminalDimensionsProvider->getTerminalDimensions();
        $this->assertCount(2, $dimensions);

        if ($this->isUnixEnvironment()) {
            $this->assertSame((int) exec('tput cols'), $dimensions[0]);
        }
    }

    public function testGetTerminalWidth()
    {
        $width = $this->terminalDimensionsProvider->getTerminalWidth();
        if ($this->isUnixEnvironment()) {
            $this->assertSame((int) exec('tput cols'), $width);
        }
    }

    /**
     * @return bool
     */
    private function isUnixEnvironment()
    {
        return '/' === DIRECTORY_SEPARATOR;
    }
}
