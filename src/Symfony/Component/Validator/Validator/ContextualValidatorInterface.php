<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Validator;

/**
 * @since  %%NextVersion%%
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ContextualValidatorInterface extends ValidatorInterface
{
    /**
     * @param $subPath
     *
     * @return ContextualValidatorInterface
     */
    public function atPath($subPath);
}
