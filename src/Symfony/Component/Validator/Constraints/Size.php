<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 *
 * @api
 */
class Size extends Constraint
{
    const TYPE_STRING     = 'string';
    const TYPE_COLLECTION = 'collection';

    public $minMessage;
    public $maxMessage;
    public $exactMessage;
    public $type;
    public $min;
    public $max;
    public $charset = 'UTF-8';

    private $stringMinMessage   = 'This value is too short. It should have {{ limit }} characters or more.';
    private $stringMaxMessage   = 'This value is too long. It should have {{ limit }} characters or less.';
    private $stringExactMessage = 'This value should have exactly {{ limit }} characters.';

    private $collectionMinMessage   = 'This collection should contain {{ limit }} elements or more.';
    private $collectionMaxMessage   = 'This collection should contain {{ limit }} elements or less.';
    private $collectionExactMessage = 'This collection should contain exactly {{ limit }} elements.';

    public function getMinMessage($type)
    {
        if (null !== $this->minMessage) {
            return $this->minMessage;
        }

        switch ($type) {
            case static::TYPE_STRING:
                return $this->stringMinMessage;
            case static::TYPE_COLLECTION:
                return $this->collectionMinMessage;
            default:
                throw new \InvalidArgumentException('Invalid type specified.');
        }
    }

    public function getMaxMessage($type)
    {
        if (null !== $this->maxMessage) {
            return $this->maxMessage;
        }

        switch ($type) {
            case static::TYPE_STRING:
                return $this->stringMaxMessage;
            case static::TYPE_COLLECTION:
                return $this->collectionMaxMessage;
            default:
                throw new \InvalidArgumentException('Invalid type specified.');
        }
    }

    public function getExactMessage($type)
    {
        if (null !== $this->exactMessage) {
            return $this->exactMessage;
        }

        switch ($type) {
            case static::TYPE_STRING:
                return $this->stringExactMessage;
            case static::TYPE_COLLECTION:
                return $this->collectionExactMessage;
            default:
                throw new \InvalidArgumentException('Invalid type specified.');
        }
    }
}
