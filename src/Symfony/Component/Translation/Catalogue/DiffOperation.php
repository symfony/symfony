<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Catalogue;

@trigger_error('The '.__NAMESPACE__.'\DiffOperation class is deprecated since version 2.8 and will be removed in 3.0. Use the TargetOperation class in the same namespace instead.', E_USER_DEPRECATED);

/**
 * Diff operation between two catalogues.
 *
 * The name of 'Diff' is misleading because the operation
 * has nothing to do with diff:
 *
 * intersection = source ∩ target = {x: x ∈ source ∧ x ∈ target}
 * all = intersection ∪ (target ∖ intersection) = target
 * new = all ∖ source = {x: x ∈ target ∧ x ∉ source}
 * obsolete = source ∖ all = source ∖ target = {x: x ∈ source ∧ x ∉ target}
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 *
 * @deprecated since version 2.8, to be removed in 3.0. Use TargetOperation instead.
 */
class DiffOperation extends TargetOperation
{
}
