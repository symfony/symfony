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
