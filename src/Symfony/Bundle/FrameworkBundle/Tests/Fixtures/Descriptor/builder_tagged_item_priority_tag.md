Services with tag `tag1`
========================

Definitions
-----------

### definition_tag_priority

- Class: `Full\Qualified\Class1`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Autoconfigured: no
- Deprecated: no
- Arguments: no
- Tag: `tag1`
    - Priority: 20
- Usages: none

### definition_method_priority

- Class: `Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor\TaggedItemWithPriorityMethodClass`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Autoconfigured: yes
- Deprecated: no
- Arguments: no
- Tag: `tag1`
    - Priority: 10
- Tag: `tag1`
    - Priority: 5
- Usages: none

### definition_no_priority

- Class: `Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor\TaggedItemWithPriorityClass`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Autoconfigured: no
- Deprecated: no
- Arguments: no
- Tag: `tag1`
    - Attr1: val1
- Usages: none

### definition_attribute_priority

- Class: `Symfony\Bundle\FrameworkBundle\Tests\Console\Descriptor\TaggedItemWithPriorityClass`
- Public: yes
- Synthetic: no
- Lazy: no
- Shared: yes
- Abstract: no
- Autowired: no
- Autoconfigured: yes
- Deprecated: no
- Arguments: no
- Tag: `tag1`
    - Attr1: val1
    - Priority: 30
- Usages: none
