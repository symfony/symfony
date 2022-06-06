<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;

/**
 * Define some ExpressionLanguage functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions()
    {
        return [
            new ExpressionFunction('is_anonymous', function () {
                return 'trigger_deprecation("symfony/security-core", "5.4", "The \"is_anonymous()\" expression function is deprecated.") || ($token && $auth_checker->isGranted("IS_ANONYMOUS"))';
            }, function (array $variables) {
                trigger_deprecation('symfony/security-core', '5.4', 'The "is_anonymous()" expression function is deprecated.');

                return $variables['token'] && $variables['auth_checker']->isGranted('IS_ANONYMOUS');
            }),

            // @deprecated remove the ternary and always use IS_AUTHENTICATED in 6.0
            new ExpressionFunction('is_authenticated', function () {
                return 'defined("'.AuthenticatedVoter::class.'::IS_AUTHENTICATED") ? $auth_checker->isGranted("IS_AUTHENTICATED") : ($token && !$auth_checker->isGranted("IS_ANONYMOUS"))';
            }, function (array $variables) {
                return \defined(AuthenticatedVoter::class.'::IS_AUTHENTICATED') ? $variables['auth_checker']->isGranted('IS_AUTHENTICATED') : ($variables['token'] && !$variables['auth_checker']->isGranted('IS_ANONYMOUS'));
            }),

            new ExpressionFunction('is_fully_authenticated', function () {
                return '$token && $auth_checker->isGranted("IS_AUTHENTICATED_FULLY")';
            }, function (array $variables) {
                return $variables['token'] && $variables['auth_checker']->isGranted('IS_AUTHENTICATED_FULLY');
            }),

            new ExpressionFunction('is_granted', function ($attributes, $object = 'null') {
                return sprintf('$auth_checker->isGranted(%s, %s)', $attributes, $object);
            }, function (array $variables, $attributes, $object = null) {
                return $variables['auth_checker']->isGranted($attributes, $object);
            }),

            new ExpressionFunction('is_remember_me', function () {
                return '$token && $auth_checker->isGranted("IS_REMEMBERED")';
            }, function (array $variables) {
                return $variables['token'] && $variables['auth_checker']->isGranted('IS_REMEMBERED');
            }),
        ];
    }
}
