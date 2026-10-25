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

class KeywordsParent extends KeywordsGrandParent
{
    use KeywordsTrait;

    /**
     * @var self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this
     */
    public $prop;

    /**
     * @param self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this $promoted
     * @param self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this $argument
     */
    public function __construct(
        public $promoted = null,
        $argument = null,
    ) {
    }

    /**
     * @return self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this
     */
    public function getAccessor()
    {
    }

    /**
     * @param self|static|parent|list<self|static|parent>|array<string, ?static>|\ArrayObject<int, parent>|$this $value
     */
    public function setMutator($value)
    {
    }
}
