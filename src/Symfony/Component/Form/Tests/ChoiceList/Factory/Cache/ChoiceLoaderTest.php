<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\ChoiceList\Factory\Cache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\Factory\Cache\ChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\FormTypeInterface;

class ChoiceLoaderTest extends TestCase
{
    public function testSameFormTypeUseCachedLoader()
    {
        $choices = ['f' => 'foo', 'b' => 'bar', 'z' => 'baz'];
        $choiceList = new ArrayChoiceList($choices);

        $type = $this->createMock(FormTypeInterface::class);
        $decorated = new CallbackChoiceLoader(static function () use ($choices) {
            return $choices;
        });
        $loader1 = new ChoiceLoader($type, $decorated);
        $loader2 = new ChoiceLoader($type, $this->createMock(ChoiceLoaderInterface::class));

        $this->assertEquals($choiceList, $loader1->loadChoiceList());
        $this->assertEquals($choiceList, $loader2->loadChoiceList());

        $this->assertSame($choices, $loader1->loadChoicesForValues($choices));
        $this->assertSame($choices, $loader2->loadChoicesForValues($choices));

        $this->assertSame($choices, $loader1->loadValuesForChoices($choices));
        $this->assertSame($choices, $loader2->loadValuesForChoices($choices));
    }
}
