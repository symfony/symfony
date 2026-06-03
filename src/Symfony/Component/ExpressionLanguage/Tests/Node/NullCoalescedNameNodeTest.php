<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Tests\Node;

use Symfony\Component\ExpressionLanguage\Node\NullCoalescedNameNode;

class NullCoalescedNameNodeTest extends AbstractNodeTestCase
{
    public static function getEvaluateData(): array
    {
        return [
            [null, new NullCoalescedNameNode('foo'), []],
        ];
    }

    public static function getCompileData(): array
    {
        return [
            ['$foo ?? null', new NullCoalescedNameNode('foo')],
        ];
    }

    public static function getDumpData(): array
    {
        return [
            ['foo ?? null', new NullCoalescedNameNode('foo')],
        ];
    }
}
