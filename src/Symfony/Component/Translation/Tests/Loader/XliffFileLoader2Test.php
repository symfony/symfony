<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Loader;

use Symfony\Component\Translation\Loader\XliffFileLoader2;

class XliffFileLoader2Test extends XliffFileLoaderTest
{
    public function testLoadWithResname()
    {
        $loader = $this->createLoader();
        $catalogue = $loader->load(__DIR__.'/../fixtures/resname.xlf', 'en', 'domain1');

        $this->assertEquals(array('foo' => 'bar', 'bar' => 'baz', 'baz' => 'foo'), $catalogue->all('domain1'));
    }

    protected function createLoader()
    {
        return new XliffFileLoader2();
    }
}
