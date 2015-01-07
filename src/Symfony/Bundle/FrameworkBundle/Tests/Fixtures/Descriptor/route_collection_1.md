route_1
-------

- Path: /hello/{name}
- Path Regex: #^/hello(?:/(?P<name>[a-z]+))?$#s
- Host: localhost
- Host Regex: #^localhost$#s
- Scheme: http|https
- Method: GET|HEAD
- Class: Symfony\Component\Routing\Route
- Defaults: 
    - `name`: Joseph
- Requirements: 
    - `name`: [a-z]+


route_2
-------

- Path: /name/add
- Path Regex: #^/name/add$#s
- Host: localhost
- Host Regex: #^localhost$#s
- Scheme: http|https
- Method: PUT|POST
- Class: Symfony\Component\Routing\Route
- Defaults: NONE
- Requirements: NONE
