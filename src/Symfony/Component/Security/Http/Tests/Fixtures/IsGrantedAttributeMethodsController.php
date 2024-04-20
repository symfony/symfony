<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Fixtures;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class IsGrantedAttributeMethodsController
{
    public function noAttribute()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN')]
    public function admin()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', subject: 'arg2Name')]
    public function withSubject($arg1Name, $arg2Name)
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', subject: ['arg1Name', 'arg2Name'])]
    public function withSubjectArray($arg1Name, $arg2Name)
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', subject: 'non_existent')]
    public function withMissingSubject()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', message: 'Not found', statusCode: 404)]
    public function notFound()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', message: 'Exception Code Http', statusCode: 404, exceptionCode: 10010)]
    public function exceptionCodeInHttpException()
    {
    }

    #[IsGranted(attribute: 'ROLE_ADMIN', message: 'Exception Code Access Denied', exceptionCode: 10010)]
    public function exceptionCodeInAccessDeniedException()
    {
    }

    #[IsGranted(attribute: new Expression('"ROLE_ADMIN" in role_names or is_granted("POST_VIEW", subject)'), subject: 'post')]
    public function withExpressionInAttribute($post)
    {
    }

    #[IsGranted(attribute: new Expression('user === subject'), subject: new Expression('args["post"].getAuthor()'))]
    public function withExpressionInSubject($post)
    {
    }

    #[IsGranted(attribute: new Expression('user === subject["author"]'), subject: [
        'author' => new Expression('args["post"].getAuthor()'),
        'alias' => 'arg2Name',
    ])]
    public function withNestedExpressionInSubject($post, $arg2Name)
    {
    }

    #[IsGranted(attribute: 'SOME_VOTER', subject: new Expression('request'))]
    public function withRequestAsSubject()
    {
    }
}
