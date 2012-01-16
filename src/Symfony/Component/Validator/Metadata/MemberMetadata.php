<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Metadata;

interface MemberMetadata
{
    function getPropertyName();

    function getClassName();

    function isPublic();

    function isProtected();

    function isPrivate();

    function isCascaded();

    function isCollectionCascaded();

    function getValue($object);

    function getReflectionMember();
}
