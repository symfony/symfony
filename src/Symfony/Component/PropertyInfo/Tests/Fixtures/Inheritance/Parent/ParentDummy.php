<?php

declare(strict_types=1);

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\Inheritance\Parent;

class ParentDummy
{
    /**
     * @return SiblingDummy
     */
    public function getSibling()
    {
        return new SiblingDummy();
    }
}
