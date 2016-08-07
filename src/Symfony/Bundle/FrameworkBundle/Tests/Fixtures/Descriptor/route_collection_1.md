route_1
-------

- Path: /hello/{name}
- Path Regex: #^/hello(?:/(?P<name>[a-z]+))?$#us
- Host: localhost
- Host Regex: #^localhost$#usi
- Scheme: http|https
- Method: GET|HEAD
- Class: Symfony\Component\Routing\Route
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
- Path Regex: #^/name/add$#us
- Host: localhost
- Host Regex: #^localhost$#usi
- Scheme: http|https
- Method: PUT|POST
- Class: Symfony\Component\Routing\Route
- Defaults: NONE
- Requirements: NO CUSTOM
- Options: 
    - `compiler_class`: Symfony\Component\Routing\RouteCompiler
    - `opt1`: val1
    - `opt2`: val2

