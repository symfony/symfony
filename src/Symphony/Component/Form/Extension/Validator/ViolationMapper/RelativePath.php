<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Validator\ViolationMapper;

use Symphony\Component\Form\FormInterface;
use Symphony\Component\PropertyAccess\PropertyPath;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RelativePath extends PropertyPath
{
    private $root;

    public function __construct(FormInterface $root, string $propertyPath)
    {
        parent::__construct($propertyPath);

        $this->root = $root;
    }

    /**
     * @return FormInterface
     */
    public function getRoot()
    {
        return $this->root;
    }
}
