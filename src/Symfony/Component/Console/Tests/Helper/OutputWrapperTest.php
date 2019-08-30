<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\OutputWrapper;

class OutputWrapperTest extends TestCase
{
    public function testBasicWrap()
    {
        $wrapper = new OutputWrapper();
        // Test UTF-8 chars + URL
        $text = 'Árvíztűrőtükörfúrógép https://github.com/symfony/symfony Lorem ipsum <comment>dolor</comment> sit amet, consectetur adipiscing elit. Praesent vestibulum nulla quis urna maximus porttitor. Donec ullamcorper risus at <error>libero ornare</error> efficitur.';
        $baseExpected = <<<EOS
Árvíztűrőtükörfúrógé
p https://github.com/symfony/symfony Lorem ipsum
<comment>dolor</comment> sit amet,
consectetur
adipiscing elit.
Praesent vestibulum
nulla quis urna
maximus porttitor.
Donec ullamcorper
risus at <error>libero
ornare</error> efficitur.
EOS;
        $result = $wrapper->wrap($text, 20);
        $this->assertEquals($baseExpected, $result);

        $URLwrappedExpected = <<<EOS
Árvíztűrőtükörfúrógé
p
https://github.com/s
ymfony/symfony Lorem
ipsum <comment>dolor</comment> sit
amet, consectetur
adipiscing elit.
Praesent vestibulum
nulla quis urna
maximus porttitor.
Donec ullamcorper
risus at <error>libero
ornare</error> efficitur.
EOS;
        $wrapper->setAllowCutUrls(false);
        $result = $wrapper->wrap($text, 20);
        $this->assertEquals($URLwrappedExpected, $result);
    }
}
