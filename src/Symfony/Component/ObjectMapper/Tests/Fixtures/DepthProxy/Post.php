<?php

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DepthProxy;

use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\ObjectMapper\Attribute\TransformAllProperties;
use Symfony\Component\ObjectMapper\Transform\UninitializeProxy;

#[Map(target: PostDto::class)]
#[TransformAllProperties(transform: new UninitializeProxy(maxDepth: 1))]
class Post
{
    public function __construct(
        public string $title,
        public object $comments, // This will be the lazy proxy
    ) {
    }
}
