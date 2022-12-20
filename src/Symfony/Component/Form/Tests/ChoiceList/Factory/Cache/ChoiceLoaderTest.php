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
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Tests\Fixtures\ArrayChoiceLoader;

class ChoiceLoaderTest extends TestCase
{
    public function testSameFormTypeUseCachedLoader()
    {
        $choices = ['f' => 'foo', 'b' => 'bar', 'z' => 'baz'];
        $choiceList = new ArrayChoiceList($choices);

        $type = new FormType();
        $decorated = new CallbackChoiceLoader(static function () use ($choices) {
            return $choices;
        });
        $loader1 = new ChoiceLoader($type, $decorated);
        $loader2 = new ChoiceLoader($type, new ArrayChoiceLoader());

        self::assertEquals($choiceList, $loader1->loadChoiceList());
        self::assertEquals($choiceList, $loader2->loadChoiceList());

        self::assertSame($choices, $loader1->loadChoicesForValues($choices));
        self::assertSame($choices, $loader2->loadChoicesForValues($choices));

        self::assertSame($choices, $loader1->loadValuesForChoices($choices));
        self::assertSame($choices, $loader2->loadValuesForChoices($choices));
    }
}
