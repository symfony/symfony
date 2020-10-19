Services with tag `tag1`
========================

Definitions
-----------

### definition_3

- Class: `Full\Qualified\Class3`
- Public: yes
- Synthetic: yes
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Autoconfigured: no
- File: `/path/to/file`
- Tag: `tag1`
    - Attr3: val3
    - Priority: 40
- Tag: `tag1`
    - Attr1: val1
    - Attr2: val2
    - Priority: 0

### definition_1

- Class: `Full\Qualified\Class1`
- Public: yes
- Synthetic: yes
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Autoconfigured: no
- File: `/path/to/file`
- Factory Service: `factory.service`
- Factory Method: `get`
- Call: `setMailer`
- Tag: `tag1`
    - Attr1: val1
    - Priority: 30
- Tag: `tag1`
    - Attr2: val2
- Tag: `tag2`

### definition_2

- Class: `Full\Qualified\Class2`
- Public: yes
- Synthetic: yes
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Autoconfigured: no
- File: `/path/to/file`
- Tag: `tag1`
    - Attr1: val1
    - Attr2: val2
    - Priority: -20
