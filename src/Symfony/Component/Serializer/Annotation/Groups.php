<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Annotation;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Annotation class for @Groups().
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD"})
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Groups
{
    /**
     * @var string[]
     */
    private $groups;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(array $data)
    {
        if (!isset($data['value']) || !$data['value']) {
            throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" cannot be empty.', get_class($this)));
        }

        $value = (array) $data['value'];
        foreach ($value as $group) {
            if (!is_string($group)) {
                throw new InvalidArgumentException(sprintf('Parameter of annotation "%s" must be a string or an array of strings.', get_class($this)));
            }
        }

        $this->groups = $value;
    }

    /**
     * Gets groups.
     *
     * @return string[]
     */
    public function getGroups()
    {
        return $this->groups;
    }
}
