# NanoDtoJsonEntityFieldBundle Docs

`NanoDtoJsonEntityFieldBundle` provides a Doctrine `dto_json` field for Symfony applications. It stores immutable DTOs as JSON envelopes and restores the concrete DTO type through a tag registry.

## Documentation

- [README](../README.md)
- [Usage](./usage.md)
- [Architecture](./architecture.md)

## Current Behavior

- DTOs extend `AbstractEntityFieldDto`
- DTOs declare their storage tag via `#[EntityFieldDtoType(...)]`
- DTO services are discovered through Symfony service loading and bundle autoconfiguration
- `dto_json` is registered as a Doctrine DBAL type through DoctrineBundle configuration
- The bundle uses the application serializer service by default
- The JSON payload stores `_dto.tag`, `_dto.instanceUuid`, `_dto.version`, and `data`
