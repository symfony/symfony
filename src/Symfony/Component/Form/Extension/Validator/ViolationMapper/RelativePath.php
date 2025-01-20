<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\ViolationMapper;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RelativePath extends PropertyPath
{
    public function __construct(
        private FormInterface $root,
        string $propertyPath,
    ) {
        parent::__construct($propertyPath);
    }

    public function getRoot(): FormInterface
    {
        return $this->root;
    }
}
