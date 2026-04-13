# Usage

## Installation

Install the bundle in the host project:

```bash
composer require nano/dto-json-entity-field-bundle
```

When Symfony Flex is not available, register the bundle manually in `config/bundles.php`:

```php
<?php

return [
    // ...
    Nano\DtoJsonEntityFieldBundle\NanoDtoJsonEntityFieldBundle::class => ['all' => true],
];
```

## Configuration

Create `config/packages/nano_dto_json_entity_field.yaml`:

```yaml
nano_dto_json_entity_field:
  doctrine:
    register_type: true
    enable_typed_field_mapper: false
  envelope:
    version: 1
```

`register_type` controls whether the bundle prepends the Doctrine DBAL type registration for `dto_json`.

`enable_typed_field_mapper` enables automatic Doctrine type assignment for properties typed as `AbstractEntityFieldDto` or subclasses.

## DTO Discovery

The bundle registers DTO classes from the Symfony service container.

If the host project already loads `App\` or `App\Dto\` as services with autoconfiguration enabled, no extra DTO-specific configuration is needed.

Example:

```yaml
services:
  App\:
    resource: '../src/'
    autowire: true
    autoconfigure: true
    exclude:
      - '../src/DependencyInjection/'
      - '../src/Entity/'
      - '../src/Kernel.php'
```

DTO classes with `#[EntityFieldDtoType(...)]` receive the bundle's DTO service tag automatically.

## Define a DTO

```php
<?php

declare(strict_types=1);

namespace App\Dto;

use Nano\DtoJsonEntityFieldBundle\Attribute\EntityFieldDtoType;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

#[EntityFieldDtoType('product_added')]
final readonly class ProductAddedDto extends AbstractEntityFieldDto
{
    public function __construct(
        string $instanceUuid,
        public string $sku,
        public int $quantity,
    ) {
        parent::__construct($instanceUuid);
    }
}
```

## Use the Field in an Entity

Explicit mapping:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;

#[ORM\Entity]
final class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'dto_json', nullable: true)]
    private ?AbstractEntityFieldDto $payload = null;
}
```

When `enable_typed_field_mapper: true` is enabled, `type: 'dto_json'` can be omitted for matching DTO-typed properties.

## Stored JSON Format

```json
{
  "_dto": {
    "tag": "product_added",
    "instanceUuid": "9d4c9a4e-1fd8-4a9d-8f89-4ce9f68f5d11",
    "version": 1
  },
  "data": {
    "sku": "ABC-123",
    "quantity": 2
  }
}
```

The payload never stores a PHP FQCN. Type resolution is based only on the tag registry.

## Fallback Registry Configuration

If a host project deliberately does not load DTO classes as Symfony services, the bundle also accepts explicit mappings:

```yaml
nano_dto_json_entity_field:
  registry:
    mappings:
      product_added: App\Dto\ProductAddedDto
```

This mapping is merged with service-discovered DTOs.
