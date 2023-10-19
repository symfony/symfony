<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV1;

class UidController
{
    #[Route(path: '/1/uuid-v1/{userId}')]
    public function anyFormat(UuidV1 $userId): Response
    {
        return new Response($userId);
    }

    #[Route(path: '/2/ulid/{id}', requirements: ['id' => '[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{22}'])]
    public function specificFormatInAttribute(Ulid $id): Response
    {
        return new Response($id);
    }

    #[Route(path: '/3/uuid-v1/{id<[0123456789ABCDEFGHJKMNPQRSTVWXYZabcdefghjkmnpqrstvwxyz]{26}>}')]
    public function specificFormatInPath(UuidV1 $id): Response
    {
        return new Response($id);
    }

    #[Route(path: '/4/uuid-v1/{postId}/custom-uid/{commentId}')]
    public function manyUids(UuidV1 $postId, TestCommentIdentifier $commentId): Response
    {
        return new Response($postId."\n".$commentId);
    }
}

class TestCommentIdentifier extends Ulid
{
}
