route_1
-------

- Path: /hello/{name}
- Path Regex: #PATH_REGEX#
- Host: localhost
- Host Regex: #HOST_REGEX#
- Scheme: http|https
- Method: GET|HEAD
- Priority: 2
- Class: Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor\RouteStub
- Defaults:
    - `name`: Joseph
- Requirements:
    - `name`: [a-z]+
- Options:
    - `compiler_class`: Symfony\Component\Routing\RouteCompiler
    - `opt1`: val1
    - `opt2`: val2


route_2
-------

- Path: /name/add
- Path Regex: #PATH_REGEX#
- Host: localhost
- Host Regex: #HOST_REGEX#
- Scheme: http|https
- Method: PUT|POST
- Priority: 1
- Class: Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor\RouteStub
- Defaults: NONE
- Requirements: NO CUSTOM
- Options:
    - `compiler_class`: Symfony\Component\Routing\RouteCompiler
    - `opt1`: val1
    - `opt2`: val2
- Condition: context.getMethod() in ['GET', 'HEAD', 'POST']

