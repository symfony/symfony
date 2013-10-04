route_1
-------

- Path: /hello/{name}
- Host: localhost
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
- Host: localhost
- Scheme: http|https
- Method: PUT|POST
- Class: Symfony\Component\Routing\Route
- Defaults: NONE
- Requirements: NONE
