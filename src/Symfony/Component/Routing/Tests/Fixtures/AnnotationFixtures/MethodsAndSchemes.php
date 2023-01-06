<?php

namespace Symfony\Component\Routing\Tests\Fixtures\AnnotationFixtures;

use Symfony\Component\Routing\Annotation\Route;

final class MethodsAndSchemes
{
    /**
     * @Route("/array-many", name="array_many", methods={"GET", "POST"}, schemes={"http", "https"})
     */
    public function arrayMany(): void
    {
    }

    /**
     * @Route("/array-one", name="array_one", methods={"GET"}, schemes={"http"})
     */
    public function arrayOne(): void
    {
    }

    /**
     * @Route("/string", name="string", methods="POST", schemes="https")
     */
    public function string(): void
    {
    }
}
