Public services
===============

Definitions
-----------

### definition_1

- Class: `Full\Qualified\Class1`
- Public: yes
- Synthetic: no
- Lazy: yes
- Shared: yes
- Abstract: yes
- Autowire: no
- Factory Class: `Full\Qualified\FactoryClass`
- Factory Method: `get`

### definition_autowired

- Class: `AutowiredService`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowire: yes

### definition_autowired_with_methods

- Class: `AutowiredService`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowire: `set*`, `addFoo`


Aliases
-------

### alias_1

- Service: `service_1`
- Public: yes

### alias_2

- Service: `service_2`
- Public: no


Services
--------

- `service_container`: `Symfony\Component\DependencyInjection\ContainerBuilder`
