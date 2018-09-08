<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Exception;

/**
 * @author Claudio Beatrice <claudi0.beatric3@gmail.com>
 */
class ExtraAttributeException extends RuntimeException
{
    /**
     * @var string
     */
    private $extraAttribute;

    public function __construct(string $extraAttribute, \Exception $previous = null)
    {
        $msg = sprintf('Extra attribute "%s" is not allowed.', $extraAttribute);

        $this->extraAttribute = $extraAttribute;

        parent::__construct($msg, 0, $previous);
    }

    /**
     * Get the extra attribute that is not allowed.
     */
    public function getExtraAttribute(): string
    {
        return $this->extraAttribute;
    }
}
