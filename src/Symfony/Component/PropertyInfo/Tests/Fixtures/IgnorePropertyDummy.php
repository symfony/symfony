<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\Groups as GroupsAnnotation;
use Symfony\Component\Serializer\Annotation\Ignore as IgnoreAnnotation;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * @author Vadim Borodavko <vadim.borodavko@gmail.com>
 */
class IgnorePropertyDummy
{
    /**
     * @GroupsAnnotation({"a"})
     */
    #[Groups(['a'])]
    public $visibleProperty;

    /**
     * @GroupsAnnotation({"a"})
     * @IgnoreAnnotation
     */
    #[Groups(['a']), Ignore]
    private $ignoredProperty;
}
