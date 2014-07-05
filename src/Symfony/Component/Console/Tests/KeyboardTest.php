<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use Symfony\Component\Console\Keyboard;

class KeyboardTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getPressedKeyProvider
     */
    public function testGetPressedKey($input, $expectedKey)
    {
        if (!$this->hasSttyAvailable()) {
            $this->markTestSkipped('`stty` is required to run this test');
        }

        $this->assertEquals($expectedKey, Keyboard::getPressedKey($this->getInputStream($input)));
    }

    public function getPressedKeyProvider()
    {
        return array(
            array("\033[A", Keyboard::KEY_UP_ARROW),
            array("\033[B", Keyboard::KEY_DOWN_ARROW),
            array(" ", Keyboard::KEY_SPACEBAR),
            array("\n", Keyboard::KEY_ENTER),
            array("\177", Keyboard::KEY_BACKSPACE),
        );
    }

    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fputs($stream, $input);
        rewind($stream);

        return $stream;
    }

    private function hasSttyAvailable()
    {
        exec('stty 2>&1', $output, $exitcode);

        return $exitcode === 0;
    }
}
