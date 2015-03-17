<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle;

use Symfony\Component\Security\Core\Authorization\ExpressionLanguage as BaseExpressionLanguage;

/**
 * Adds some functions to the default Symfony Debug ExpressionLanguage.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ExpressionLanguage extends BaseExpressionLanguage
{
    protected function registerFunctions()
    {
        parent::registerFunctions();
        $this->register('strpos', function ($haystack, $needle) {
            return sprintf('strpos(%s, %s)', $haystack, $needle);
        }, function (array $variables, $haystack, $needle) {
            return strpos($haystack, $needle);
        });
    }
}
