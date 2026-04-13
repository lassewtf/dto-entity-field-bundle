# Architecture

## Overview

The bundle is split into small runtime components with narrow responsibilities:

- `Dto\AbstractEntityFieldDto`
- `Attribute\EntityFieldDtoType`
- `Registry\EntityFieldDtoRegistry`
- `Envelope\DtoJsonEnvelope` and `DtoJsonEnvelopeFactory`
- `Codec\DtoJsonCodec`
- `Doctrine\Type\DtoJsonType`
- `Doctrine\TypedFieldMapper\EntityFieldDtoTypedFieldMapper`
- compiler passes for DTO registration and typed field mapper integration

## Type Resolution

The concrete DTO type is resolved through a tag registry.

- DTO classes declare their tag with `#[EntityFieldDtoType('...')]`
- the compiler pass collects DTO services tagged through autoconfiguration
- the registry stores `tag -> class` and `class -> tag`
- JSON payloads persist only the DTO tag, not the PHP class name

## Serialization

`DtoJsonCodec` handles DTO normalization and denormalization.

- encoding:
  - resolves the DTO tag through the registry
  - normalizes the DTO with the application serializer service
  - removes `instanceUuid` from the data payload
  - creates the JSON envelope
- decoding:
  - validates the envelope
  - resolves the concrete class from the tag
  - merges `instanceUuid` back into the denormalization payload
  - denormalizes to the concrete DTO class

## Envelope Format

The envelope is technical and stable:

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

`DtoJsonEnvelopeFactory` validates structure and reserved metadata rules.

## Doctrine Integration

`DtoJsonType` extends Doctrine's `JsonType`.

- `null` roundtrips as `null`
- DTO values are encoded to an envelope array and then serialized by DBAL
- database payloads are decoded to arrays by DBAL and then converted back to DTOs

The DBAL type is registered through DoctrineBundle configuration prepended by the bundle when `register_type` is enabled.

## Runtime Bridge

Doctrine DBAL types are not constructed through Symfony DI. The bundle therefore uses `DtoJsonTypeRuntime` as a small bridge from the static DBAL type instance to the Symfony-managed codec service.

The runtime bridge stores the container reference on bundle boot and resolves the codec lazily.

## Typed Field Mapper

`EntityFieldDtoTypedFieldMapper` is optional.

When enabled:

- Doctrine properties typed as `AbstractEntityFieldDto` or subclasses get `dto_json` automatically
- explicit Doctrine field types are not overwritten

The bundle attaches the mapper to Doctrine ORM configuration services during container compilation.

## Current Constraints

- DTO discovery depends on Symfony service loading unless explicit registry mappings are configured
- the bundle does not use serializer discriminator metadata
- the bundle does not perform payload upcasting or migration
- `instanceUuid` is stored as a string and is carried through unchanged on hydration
