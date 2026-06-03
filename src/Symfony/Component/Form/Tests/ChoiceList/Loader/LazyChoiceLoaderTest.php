<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\Loader\LazyChoiceLoader;
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;

class LazyChoiceLoaderTest extends TestCase
{
    private LazyChoiceLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new LazyChoiceLoader(new ArrayChoiceLoader(['A', 'B', 'C']));
    }

    public function testInitialEmptyChoiceListLoading()
    {
        $this->assertSame([], $this->loader->loadChoiceList()->getChoices());
    }

    public function testOnDemandChoiceListAfterLoadingValuesForChoices()
    {
        $this->loader->loadValuesForChoices(['A']);
        $this->assertSame(['A' => 'A'], $this->loader->loadChoiceList()->getChoices());
    }

    public function testOnDemandChoiceListAfterLoadingChoicesForValues()
    {
        $this->loader->loadChoicesForValues(['B']);
        $this->assertSame(['B' => 'B'], $this->loader->loadChoiceList()->getChoices());
    }

    public function testOnDemandChoiceList()
    {
        $this->loader->loadValuesForChoices(['A']);
        $this->loader->loadChoicesForValues(['B']);
        $this->assertSame(['B' => 'B'], $this->loader->loadChoiceList()->getChoices());
    }
}
