<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image;

use Symfony\Component\Image\Exception\InvalidArgumentException;

class Profile implements ProfileInterface
{
    private $data;
    private $name;

    public function __construct($name, $data)
    {
        $this->name = $name;
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Creates a profile from a path to a file
     *
     * @param String $path
     *
     * @return Profile
     *
     * @throws InvalidArgumentException In case the provided path is not valid
     */
    public static function fromPath($path)
    {
        if (!file_exists($path) || !is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('Path %s is an invalid profile file or is not readable', $path));
        }

        return new static(basename($path), file_get_contents($path));
    }
}
