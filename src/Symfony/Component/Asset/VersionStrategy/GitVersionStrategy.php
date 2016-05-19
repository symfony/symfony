<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\VersionStrategy;

use Symfony\Component\Asset\Exception\InvalidArgumentException;

/**
 * Returns the version as git hash from latest commit.
 *
 * @author Evgeniy Sokolov <ewgraf@gmail.com>
 */
class GitVersionStrategy extends StaticVersionStrategy
{
    /**
     * @param string $version Version number
     * @param string $format  Url format
     * @throws InvalidArgumentException
     */
    public function __construct($version, $format = null)
    {
        if (!$version) {
            throw new InvalidArgumentException("Version must be git a hash.");
        }

        parent::__construct($version, $format);
    }

}
