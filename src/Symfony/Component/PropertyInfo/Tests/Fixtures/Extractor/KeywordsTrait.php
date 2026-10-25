<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\Extractor;

trait KeywordsTrait
{
    /**
     * @var self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this
     */
    public $traitProp;

    /**
     * @return self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this
     */
    public function getTraitAccessor()
    {
    }

    /**
     * @param self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this $value
     */
    public function setTraitMutator($value)
    {
    }
}
