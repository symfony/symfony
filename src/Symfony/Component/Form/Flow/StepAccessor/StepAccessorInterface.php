<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow\StepAccessor;

/**
 * Reads from or writes the current step name to a provided data source.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface StepAccessorInterface
{
    public function getStep(object|array $data, ?string $default = null): ?string;

    public function setStep(object|array &$data, string $step): void;
}
