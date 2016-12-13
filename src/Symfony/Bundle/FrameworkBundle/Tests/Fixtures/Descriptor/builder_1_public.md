Public services
===============

Definitions
-----------

definition_1
~~~~~~~~~~~~

- Class: `Full\Qualified\Class1`
- Public: yes
- Synthetic: no
- Lazy: yes
- Shared: yes
- Abstract: yes
- Autowired: no
- Factory Class: `Full\Qualified\FactoryClass`
- Factory Method: `get`
- Usages:
    - `definition_3`
    - `definition_4`

definition_3
~~~~~~~~~~~~

- Class: `Full\Qualified\Class3`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Usages:
    - `definition_4`

definition_4
~~~~~~~~~~~~

- Class: `Full\Qualified\Class4`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Call: `setFoo`
- Usages: -


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
