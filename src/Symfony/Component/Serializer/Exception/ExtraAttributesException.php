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
 * @author Julien DIDIER <julien@didier.io>
 */
class ExtraAttributesException extends RuntimeException
{
    public function __construct(
        private readonly array $extraAttributes,
        ?\Throwable $previous = null,
    ) {
        $msg = \sprintf('Extra attributes are not allowed ("%s" %s unknown).', implode('", "', array_keys($extraAttributes)), \count($extraAttributes) > 1 ? 'are' : 'is');

        parent::__construct($msg, 0, $previous);
    }

    /**
     * Get the unallowed attributes top-level name.
     */
    public function getExtraAttributes(): array
    {
        return array_keys($this->extraAttributes);
    }

    public function getValues(): array
    {
        return $this->extraAttributes;
    }
}
