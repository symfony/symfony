Public and private services
===========================

Definitions
-----------

definition_1
~~~~~~~~~~~~

- Class: `Full\Qualified\Class1`
- Scope: `container`
- Public: yes
- Synthetic: no
- Lazy: yes
- Shared: yes
- Synchronized: no
- Abstract: yes
- Autowired: no
- Factory Class: `Full\Qualified\FactoryClass`
- Factory Method: `get`

definition_2
~~~~~~~~~~~~

- Class: `Full\Qualified\Class2`
- Scope: `container`
- Public: no
- Synthetic: yes
- Lazy: no
- Shared: yes
- Synchronized: no
- Abstract: no
- Autowired: no
- File: `/path/to/file`
- Factory Service: `factory.service`
- Factory Method: `get`
- Tag: `tag1`
    - Attr1: val1
    - Attr2: val2
- Tag: `tag1`
    - Attr3: val3
- Tag: `tag2`


Aliases
-------

alias_1
~~~~~~~

- Service: `service_1`
- Public: yes

alias_2
~~~~~~~

- Service: `service_2`
- Public: no


Services
--------

- `service_container`: `Symfony\Component\DependencyInjection\ContainerBuilder`
