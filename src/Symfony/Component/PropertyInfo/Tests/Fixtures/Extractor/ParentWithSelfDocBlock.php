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

class ParentWithSelfDocBlock
{
    /**
     * @var self
     */
    public $selfProp;

    /**
     * @return self
     */
    public function getSelfAccessor()
    {
        return $this;
    }

    /**
     * @param self $value
     */
    public function setSelfMutator($value)
    {
    }
}

class ChildWithSelfDocBlock extends ParentWithSelfDocBlock
{
}

trait TraitWithSelfDocBlock
{
    /**
     * @var self
     */
    public $selfTraitProp;

    /**
     * @return self
     */
    public function getSelfTraitAccessor()
    {
        return $this;
    }

    /**
     * @param self $value
     */
    public function setSelfTraitMutator($value)
    {
    }
}

class ClassUsingTraitWithSelfDocBlock
{
    use TraitWithSelfDocBlock;
}

class ParentUsingTraitWithSelfDocBlock
{
    use TraitWithSelfDocBlock;
}

class ChildOfParentUsingTrait extends ParentUsingTraitWithSelfDocBlock
{
}

trait InnerTraitWithSelf
{
    /**
     * @var self
     */
    public $innerSelfProp;
}

trait OuterTrait
{
    use InnerTraitWithSelf;
}

class ClassUsingNestedTrait
{
    use OuterTrait;
}

class ParentWithPromotedSelfDocBlock
{
    /**
     * @param self $promotedSelfProp
     */
    public function __construct(
        public $promotedSelfProp = null,
    ) {
    }
}

class ChildOfParentWithPromotedSelfDocBlock extends ParentWithPromotedSelfDocBlock
{
}
