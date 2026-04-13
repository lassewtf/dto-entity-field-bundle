# NanoDtoJsonEntityFieldBundle

`NanoDtoJsonEntityFieldBundle` provides a Doctrine `dto_json` field for Symfony applications. Database values are stored as JSON envelopes, while PHP code works with immutable DTO objects resolved through a tag registry.

## Features

- Doctrine DBAL type: `dto_json`
- Immutable DTO base class with `instanceUuid`
- Tag-based polymorphic DTO resolution without serializer discriminators
- DTO discovery through Symfony service loading plus attribute autoconfiguration
- Optional Doctrine `TypedFieldMapper`
- Strict envelope validation and project-grade exceptions

## Installation and Local Usage

```bash
composer require nano/dto-json-entity-field-bundle
```

Symfony Flex will register the bundle automatically. Without Flex, register `Nano\DtoJsonEntityFieldBundle\NanoDtoJsonEntityFieldBundle` manually.

To use a local checkout before consuming the package from Packagist, add a `path` repository in the host project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../dto-entity-field-bundle",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "nano/dto-json-entity-field-bundle": "*@dev"
  }
}
```

Then run:

```bash
composer update nano/dto-json-entity-field-bundle
```

## Bundle Configuration

Create `config/packages/nano_dto_json_entity_field.yaml`:

```yaml
nano_dto_json_entity_field:
  doctrine:
    register_type: true
    enable_typed_field_mapper: false
  envelope:
    version: 1
```

`register_type` registers the Doctrine DBAL type `dto_json`.

`enable_typed_field_mapper` enables automatic Doctrine field type assignment for DTO-typed properties.

## DTO Definition

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

## DTO Discovery Through Symfony Services

DTO discovery relies on Symfony container services. The host project must load DTO classes through its normal `services` configuration so the bundle's compiler pass can see them.

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

Classes carrying `#[EntityFieldDtoType(...)]` are automatically tagged by the bundle and registered in the DTO registry.

## Entity Mapping

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

Optional fallback if the host project deliberately does not load DTOs as services:

```yaml
nano_dto_json_entity_field:
  registry:
    mappings:
      product_added: App\Dto\ProductAddedDto
```

## Typed Field Mapper

When enabled, Doctrine fields typed as `AbstractEntityFieldDto` or a subclass can omit `type: 'dto_json'` as long as no explicit Doctrine type is already set.

## Design Notes

- No serializer discriminator map is used.
- No PHP FQCN is written into the JSON payload.
- DTO resolution is done exclusively through the stored tag and the runtime registry.
- `instanceUuid` is preserved when hydrating from the database and must change whenever DTO content changes.

## Testing

```bash
composer install
vendor/bin/phpunit
```

## Documentation

- [docs/index.md](./docs/index.md)
- [docs/usage.md](./docs/usage.md)
- [docs/architecture.md](./docs/architecture.md)
