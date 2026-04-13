# NanoDtoJsonEntityFieldBundle Docs

## Overview

`NanoDtoJsonEntityFieldBundle` provides a generic Doctrine `dto_json` field for Symfony applications. It stores DTO payloads as JSON envelopes in the database and restores typed immutable DTO objects in PHP.

## Documentation

- [README](../README.md)
- [RFC: Symfony Bundle für generisches `dto_json` Doctrine-ORM-Field](./symfony-dto-json-bundle-rfc.md)
- [Agent Brief: Symfony Bundle für generisches `dto_json`-Doctrine-Field](./symfony-dto-json-bundle-agent-brief.md)

## Key Concepts

- DTO classes extend `AbstractEntityFieldDto`
- DTO classes declare their storage tag via `#[EntityFieldDtoType(...)]`
- DTO discovery happens through Symfony service loading plus bundle autoconfiguration
- Doctrine persists a technical JSON envelope containing `_dto` metadata and `data`
- The concrete DTO class is resolved through the tag registry, not serializer discriminators
