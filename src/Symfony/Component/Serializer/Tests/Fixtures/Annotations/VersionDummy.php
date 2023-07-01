<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Annotation\Version;
use Symfony\Component\Serializer\Annotation\VersionConstraint;

/**
 * @author Olivier MICHAUD <olivier@micoli.org>
 */
class VersionDummy
{
    private $foo;

    /**
     * @Version
     */
    public string $objectVersion;

    /**
     * @VersionConstraint(
     *     since="1.1",
     *     until="1.5"
     * )
     */
    public ?string $versionedProperty;

    /**
     * @var string
     * @VersionConstraint(
     *     since="1.1",
     *     until="1.5"
     * )
     */
    public $versionedProperty2;
}
