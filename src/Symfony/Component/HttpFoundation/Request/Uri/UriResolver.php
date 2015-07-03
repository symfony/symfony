<?php
/**
 * Created by PhpStorm.
 * User: yosefderay
 * Date: 7/3/15
 * Time: 2:12 PM
 */

namespace Symfony\Component\HttpFoundation\Request\Uri;


use Symfony\Component\HttpFoundation\Request;

class UriResolver implements UriResolverInterface
{
    /**
     * @var UriResolverInterface[]
     */
    private $resolvers = array();

    public static function create()
    {
        $resolver = new static();
        $resolver
            ->add(new ApacheRequestUriResolver())
            ->add(new IISWithMicrosoftRewriteModuleUriResolver())
            ->add(new IISWithASAPIRewriteUriResolver())
            ->add(new IIS7WithUrlRewriteUriResolver())
            ->add(new RequestUriUriResolver())
            ->add(new OrigPathInfoUriResolver())
        ;
        return $resolver;
    }

    public function resolveUri(Request $request)
    {
        $requestUri = false;

        foreach ($this->resolvers as $uriResolver) {
            $requestUri = $uriResolver->resolveUri($request);

            if ($requestUri !== false) {
                break;
            }
        }

        return $requestUri;
    }

    /**
     * @param UriResolverInterface $resolver
     * @return $this
     */
    public function add(UriResolverInterface $resolver)
    {
        array_unshift($this->resolvers, $resolver);
        return $this;
    }
}