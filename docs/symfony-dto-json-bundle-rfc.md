# RFC: Symfony Bundle für generisches `dto_json` Doctrine-ORM-Field

## Status

Entwurf v1

## Ziel

Dieses Dokument beschreibt die technische Umsetzung eines wiederverwendbaren Symfony-Bundles, das ein generisches Doctrine-ORM-Feld `dto_json` bereitstellt.

Das Feld speichert Daten in der Datenbank als JSON und liefert in PHP typisierte DTO-Objekte zurück.

Das Feld ist polymorph:
- mehrere konkrete DTO-Klassen sind erlaubt,
- alle DTOs müssen von einer gemeinsamen abstrakten Basisklasse erben,
- die konkrete Klasse wird nicht über Serializer-Discriminator-Metadaten bestimmt,
- sondern über einen gespeicherten DTO-Tag und eine Registry.

---

## Nicht-Ziele

Nicht Teil von v1:
- kein automatisches In-Place-Update bestehender DTO-Objekte
- keine Nutzung von FQCNs im JSON
- keine Abhängigkeit auf Symfony-Serializer-Discriminator-Mapping
- keine MongoDB- oder ODM-Integration
- keine tiefe JSON-Pfad-Query-Abstraktion
- keine automatische Migration alter Payload-Versionen in v1

---

## Fachliche Regeln

### Basisklasse

Alle DTOs, die in `dto_json` gespeichert werden dürfen, müssen von `AbstractEntityFieldDto` erben.

```php
abstract class AbstractEntityFieldDto
{
    public function __construct(
        public readonly string $instanceUuid,
    ) {}
}
```

### UUID-Regel

- `instanceUuid` ist immer ein `string`
- beim Laden aus der DB wird die gespeicherte UUID übernommen
- bei jeder inhaltlichen Änderung wird eine neue UUID erzeugt
- DTOs sind immutable
- Änderungen erzeugen immer neue DTO-Instanzen

### Typauflösung

- jede konkrete DTO-Klasse besitzt einen stabilen Tag
- der Tag wird im JSON gespeichert
- beim Laden wird der Tag über eine Registry in eine konkrete DTO-Klasse aufgelöst

### Persistenzformat

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

---

## Gesamtarchitektur

Das Bundle besteht aus sechs Kernschichten:

1. **DTO-Basis und DTO-Tagging**
2. **Registry** für `tag <-> class`
3. **Envelope Factory / Value Object**
4. **Codec** für Normalize/Denormalize
5. **Doctrine DBAL Type** `dto_json`
6. **Symfony Bundle Integration** mit DI, Compiler Pass und optionalem TypedFieldMapper

---

## Komponentenübersicht

```text
Bundle
├── Dto
│   ├── AbstractEntityFieldDto
│   └── Contracts / Marker Interfaces (optional)
├── Attribute
│   └── EntityFieldDtoType
├── Registry
│   ├── EntityFieldDtoRegistryInterface
│   └── EntityFieldDtoRegistry
├── Envelope
│   ├── DtoJsonEnvelope
│   └── DtoJsonEnvelopeFactory
├── Codec
│   ├── DtoJsonCodecInterface
│   └── DtoJsonCodec
├── Doctrine
│   ├── Type
│   │   └── DtoJsonType
│   │   └── DtoJsonTypeRuntime
│   └── TypedFieldMapper
│       └── EntityFieldDtoTypedFieldMapper
├── DependencyInjection
│   ├── DtoJsonExtension
│   └── Compiler
│       └── RegisterEntityFieldDtoPass
└── Exception
    ├── UnknownDtoTagException
    ├── UnsupportedDtoClassException
    ├── InvalidDtoPayloadException
    └── DtoJsonConfigurationException
```

---

## Datenfluss

## Persist

```text
Entity Field DTO
  -> DtoJsonType::convertToDatabaseValue()
    -> DtoJsonCodec::encode()
      -> Registry::tagFor(class)
      -> Serializer::normalize(dto)
      -> EnvelopeFactory::createFromDto(tag, instanceUuid, normalizedData)
    -> JsonType parent conversion
  -> DB JSON
```

## Load

```text
DB JSON
  -> DtoJsonType::convertToPHPValue()
    -> JsonType parent conversion
    -> DtoJsonCodec::decode()
      -> EnvelopeFactory::fromArray(payload)
      -> Registry::classForTag(tag)
      -> merge instanceUuid into DTO constructor payload
      -> Serializer::denormalize(data, concreteClass)
  -> DTO Objekt
```

---

## Öffentliche API

## 1. Basisklasse

```php
abstract class AbstractEntityFieldDto
{
    public function __construct(
        public readonly string $instanceUuid,
    ) {}
}
```

## 2. DTO-Tag-Attribut

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final class EntityFieldDtoType
{
    public function __construct(
        public readonly string $tag,
    ) {}
}
```

## 3. Registry Interface

```php
interface EntityFieldDtoRegistryInterface
{
    public function tagFor(string $class): string;

    /**
     * @return class-string<AbstractEntityFieldDto>
     */
    public function classForTag(string $tag): string;

    public function isSupported(string $class): bool;
}
```

## 4. Codec Interface

```php
interface DtoJsonCodecInterface
{
    public function encode(AbstractEntityFieldDto $dto): array;

    public function decode(array $payload): AbstractEntityFieldDto;
}
```

---

## DTO-Definition und Änderungsmodell

### Beispiel DTO

```php
#[EntityFieldDtoType('product_added')]
final class ProductAddedDto extends AbstractEntityFieldDto
{
    public function __construct(
        string $instanceUuid,
        public readonly string $sku,
        public readonly int $quantity,
    ) {
        parent::__construct($instanceUuid);
    }

    public function withQuantity(int $quantity): self
    {
        return new self(
            instanceUuid: self::generateInstanceUuid(),
            sku: $this->sku,
            quantity: $quantity,
        );
    }

    private static function generateInstanceUuid(): string
    {
        return \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
    }
}
```

### Regel

DTOs dürfen keine fachlichen Setter haben. Änderungen erfolgen über `with*`-Methoden oder Fabriken, die immer ein neues Objekt mit neuer `instanceUuid` erzeugen.

---

## Registry-Design

## Quelle der Registrierungen

v1 soll beide Wege ermöglichen, aber einen klaren Default haben:

### Primär: Symfony Service Tag + Compiler Pass

Für v1 soll das Bundle den Symfony-Container als Quelle der DTO-Registrierung nutzen.

Das Host-Projekt lädt seine DTO-Klassen als Services. Das Attribut `#[EntityFieldDtoType]` bleibt die deklarative Quelle des Tags auf der DTO-Klasse. Das Bundle kann per Autoconfiguration dafür sorgen, dass solche Klassen automatisch ein DTO-spezifisches Service-Tag erhalten.

Ein Compiler Pass sammelt diese Definitionen und baut daraus eine Map:
- `tag => class`
- `class => tag`

### Sekundär: explizite Bundle-Konfiguration

Falls ein Host-Projekt DTO-Klassen bewusst nicht als Services lädt, darf das Bundle alternativ eine explizite Konfiguration von Tag/Class-Mappings anbieten.

### Wichtige Präzisierung

Ein Compiler Pass sieht nur Container-Definitionen. Er darf daher nicht als alleinige Discovery-Strategie für beliebige DTO-Klassen im Host-Projekt vorausgesetzt werden.

### Empfehlung

Für v1:
- Tag wird an der Klasse mit `#[EntityFieldDtoType]` deklariert
- DTO-Klassen werden im Host-Projekt als Services geladen
- Autoconfiguration oder explizites Service-Tagging markiert unterstützte DTO-Klassen
- der Compiler Pass validiert und registriert nur die DTOs, die dem Container tatsächlich bekannt sind
- explizite Bundle-Konfiguration bleibt ein Fallback, nicht der Default

## Anforderungen an die Registry

- Tag muss eindeutig sein
- Klasse muss eindeutig sein
- Klasse muss von `AbstractEntityFieldDto` erben
- unbekannte Tags lösen beim Laden eine Exception aus
- unbekannte Klassen lösen beim Persist eine Exception aus

### Beispiel-Implementierung

```php
final class EntityFieldDtoRegistry implements EntityFieldDtoRegistryInterface
{
    /** @param array<string, class-string<AbstractEntityFieldDto>> $tagToClass */
    public function __construct(
        private readonly array $tagToClass,
    ) {}

    public function tagFor(string $class): string
    {
        $tag = array_search($class, $this->tagToClass, true);

        if (!is_string($tag)) {
            throw new UnsupportedDtoClassException($class);
        }

        return $tag;
    }

    public function classForTag(string $tag): string
    {
        return $this->tagToClass[$tag]
            ?? throw new UnknownDtoTagException($tag);
    }

    public function isSupported(string $class): bool
    {
        return in_array($class, $this->tagToClass, true);
    }
}
```

---

## Envelope-Design

## Envelope Value Object

```php
final class DtoJsonEnvelope
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $tag,
        public readonly string $instanceUuid,
        public readonly int $version,
        public readonly array $data,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            '_dto' => [
                'tag' => $this->tag,
                'instanceUuid' => $this->instanceUuid,
                'version' => $this->version,
            ],
            'data' => $this->data,
        ];
    }
}
```

## Envelope Factory

Aufgaben:
- Envelope aus DTO-Tag, UUID und Normalizer-Daten erzeugen
- Envelope aus rohem DB-Array validiert rekonstruieren
- technische Felder aus `data` entfernen oder zusammenführen

### Validierung

Die Factory muss prüfen:
- `_dto` vorhanden
- `_dto.tag` ist String
- `_dto.instanceUuid` ist String
- `_dto.version` ist int oder Standardwert 1
- `data` ist Array

---

## Codec-Design

## Zweck

Der Codec kapselt die eigentliche Fachlogik von:
- DTO -> Envelope Array
- Envelope Array -> DTO

## Encode

### Eingabe
- `AbstractEntityFieldDto $dto`

### Schritte
1. konkrete Klasse bestimmen
2. Registry fragt Tag ab
3. Serializer normalisiert DTO zu Array
4. `instanceUuid` wird aus dem DTO gelesen
5. technische Felder werden aus dem Nutzdatenarray entfernt, falls nötig
6. Envelope wird erzeugt
7. Array zurückgeben

### Wichtige Regel

Die serialisierten `data` dürfen nicht doppelt technische Metadaten tragen. Es gibt zwei mögliche Strategien:

#### Strategie A: `instanceUuid` in `data` belassen
Vorteil: einfache Denormalisierung.
Nachteil: Redundanz.

#### Strategie B: `instanceUuid` aus `data` entfernen und beim Decode wieder ergänzen
Vorteil: sauberes Envelope.
Nachteil: etwas mehr Codec-Logik.

### Empfehlung für v1

**Strategie B** verwenden.

## Decode

### Eingabe
- Array aus DB-JSON

### Schritte
1. Envelope validieren
2. Tag aus Envelope lesen
3. Klasse über Registry bestimmen
4. `instanceUuid` in `data` einsetzen
5. Serializer `denormalize()` gegen konkrete Klasse aufrufen
6. Ergebnis auf `AbstractEntityFieldDto` validieren

### Serializer-Kontext

v1 nutzt bewusst **kein** `OBJECT_TO_POPULATE` als Kernmechanik.

Begründung:
- DTOs sind immutable
- inhaltliche Änderung erzeugt neue UUID
- `object_to_populate` ist eher für Updates vorhandener Objekte geeignet

Optional kann der Codec so gebaut werden, dass ein späteres `object_to_populate` über Kontext unterstützt wird.

### Beispiel

```php
final class DtoJsonCodec implements DtoJsonCodecInterface
{
    public function __construct(
        private readonly \Symfony\Component\Serializer\SerializerInterface $serializer,
        private readonly EntityFieldDtoRegistryInterface $registry,
        private readonly DtoJsonEnvelopeFactory $envelopeFactory,
    ) {}

    public function encode(AbstractEntityFieldDto $dto): array
    {
        $tag = $this->registry->tagFor($dto::class);
        $normalized = $this->serializer->normalize($dto, 'array');

        if (!is_array($normalized)) {
            throw new InvalidDtoPayloadException('DTO normalization must return an array.');
        }

        unset($normalized['instanceUuid']);

        return $this->envelopeFactory
            ->create($tag, $dto->instanceUuid, $normalized)
            ->toArray();
    }

    public function decode(array $payload): AbstractEntityFieldDto
    {
        $envelope = $this->envelopeFactory->fromArray($payload);
        $class = $this->registry->classForTag($envelope->tag);

        $data = [
            'instanceUuid' => $envelope->instanceUuid,
            ...$envelope->data,
        ];

        $dto = $this->serializer->denormalize($data, $class, 'array');

        if (!$dto instanceof AbstractEntityFieldDto) {
            throw new InvalidDtoPayloadException(sprintf(
                'Decoded DTO must extend %s.',
                AbstractEntityFieldDto::class,
            ));
        }

        return $dto;
    }
}
```

---

## Doctrine Type Design

## Name

- Type Name: `dto_json`
- Basis: Erweiterung von `Doctrine\DBAL\Types\JsonType`

## Verantwortung

- null korrekt behandeln
- nicht unterstützte Werte früh ablehnen
- Encode/Decode an Codec delegieren
- DB-seitige JSON-Konvertierung dem JsonType überlassen

## Beispiel

```php
final class DtoJsonType extends \Doctrine\DBAL\Types\JsonType
{
    public const NAME = 'dto_json';

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToDatabaseValue(mixed $value, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof AbstractEntityFieldDto) {
            throw \Doctrine\DBAL\Types\ConversionException::conversionFailedInvalidType(
                $value,
                self::NAME,
                ['null', AbstractEntityFieldDto::class],
            );
        }

        return parent::convertToDatabaseValue(
            DtoJsonTypeRuntime::codec()->encode($value),
            $platform,
        );
    }

    public function convertToPHPValue(mixed $value, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): mixed
    {
        $decoded = parent::convertToPHPValue($value, $platform);

        if ($decoded === null) {
            return null;
        }

        if (!is_array($decoded)) {
            throw \Doctrine\DBAL\Types\ConversionException::conversionFailed($value, self::NAME);
        }

        return DtoJsonTypeRuntime::codec()->decode($decoded);
    }
}
```

## Wichtige Randbedingung

Der Type bleibt zustandslos. Feldspezifische Konfiguration gehört nicht in die Type-Instanz.

## Runtime-Anbindung

Doctrine-Types werden in der üblichen Symfony-Integration standardmäßig per Klassenname registriert. Für v1 soll `DtoJsonType` deshalb nicht auf Konstruktor-Injection von Symfony-Services angewiesen sein.

Stattdessen gilt:
- `DtoJsonType` bleibt ein zustandsloser Flyweight-Type
- `DtoJsonCodec`, `Registry` und `EnvelopeFactory` bleiben normale Symfony-Services
- das Bundle initialisiert eine kleine interne Runtime-Bridge, über die der Type den `Codec` beziehen kann
- diese Bridge ist technische Infrastruktur; fachliche Logik bleibt im `Codec`

---

## TypedFieldMapper

## Ziel

Optional automatische Zuordnung von Feldern, deren PHP-Typ `AbstractEntityFieldDto` oder eine Unterklasse ist.

## Verhalten

- wenn ein Feld bereits einen Doctrine-Type hat, nichts ändern
- wenn ein Feld auf `AbstractEntityFieldDto` oder Subklasse typisiert ist, `type = dto_json` setzen

## Beispielidee

```php
final class EntityFieldDtoTypedFieldMapper implements \Doctrine\ORM\Mapping\TypedFieldMapper
{
    public function validateAndComplete(array $mapping, \ReflectionProperty $field): array
    {
        if (isset($mapping['type'])) {
            return $mapping;
        }

        $type = $field->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return $mapping;
        }

        $class = $type->getName();

        if (is_a($class, AbstractEntityFieldDto::class, true)) {
            $mapping['type'] = DtoJsonType::NAME;
        }

        return $mapping;
    }
}
```

## Empfehlung

Als optionales Feature implementieren und per Bundle-Konfiguration aktivierbar machen.

---

## Dependency Injection und Bundle-Bootstrapping

## Services

Das Bundle registriert mindestens:
- Registry
- Codec
- EnvelopeFactory
- Runtime-Bridge-Initialisierung für den Doctrine Type
- optional TypedFieldMapper
- Service-Autoconfiguration für DTO-Klassen mit `#[EntityFieldDtoType]`

## Compiler Pass

`RegisterEntityFieldDtoPass` soll:
- registrierte DTO-Services sammeln und validieren
- Tag-Konflikte erkennen
- Klassenvalidierung durchführen
- Registry-Mapping bauen

## Bundle-Konfiguration

Beispielkonfiguration:

```yaml
entity_field_dto:
  doctrine:
    register_type: true
    enable_typed_field_mapper: true
  envelope:
    version: 1
```

Optional später:

```yaml
entity_field_dto:
  dto_paths:
    - '%kernel.project_dir%/src/Dto/EntityField'
```

Für v1 ist Service-Registrierung über den Symfony-Container robuster als implizites Filesystem-Scanning.
Explizite Bundle-Konfiguration bleibt ein Fallback für Projekte, die DTO-Klassen nicht als Services laden.

---

## Nutzung im Projekt

## DTO definieren

```php
#[EntityFieldDtoType('coupon_applied')]
final class CouponAppliedDto extends AbstractEntityFieldDto
{
    public function __construct(
        string $instanceUuid,
        public readonly string $code,
    ) {
        parent::__construct($instanceUuid);
    }

    public static function create(string $code): self
    {
        return new self(
            instanceUuid: \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
            code: $code,
        );
    }
}
```

## Entity-Feld verwenden

```php
#[\Doctrine\ORM\Mapping\Entity]
final class Order
{
    #[\Doctrine\ORM\Mapping\Column(type: 'dto_json', nullable: true)]
    private ?AbstractEntityFieldDto $payload = null;

    public function payload(): ?AbstractEntityFieldDto
    {
        return $this->payload;
    }

    public function changePayload(?AbstractEntityFieldDto $payload): void
    {
        $this->payload = $payload;
    }
}
```

Wenn der TypedFieldMapper aktiviert ist, kann `type: 'dto_json'` optional entfallen, sofern der PHP-Typ passend ist.

---

## Fehler- und Validierungsmodell

Das Bundle soll in diesen Fällen mit klaren Exceptions abbrechen:

- unbekannter DTO-Tag
- DTO-Klasse nicht unterstützt
- DTO-Klasse erbt nicht von `AbstractEntityFieldDto`
- Envelope hat falsche Struktur
- `instanceUuid` fehlt oder ist kein String
- Serializer liefert kein Array beim Normalize
- Serializer liefert kein `AbstractEntityFieldDto` beim Decode
- doppelte Tags
- doppelt registrierte Klassen

Exception-Klassen:
- `UnknownDtoTagException`
- `UnsupportedDtoClassException`
- `InvalidDtoPayloadException`
- `DtoJsonConfigurationException`

---

## Teststrategie

## Unit Tests

### Registry
- `tagFor()` korrekt
- `classForTag()` korrekt
- unbekannter Tag schlägt fehl
- unbekannte Klasse schlägt fehl
- Duplicate Validation

### EnvelopeFactory
- gültiges Array wird korrekt gelesen
- fehlende `_dto`-Struktur schlägt fehl
- ungültige `instanceUuid` schlägt fehl
- Standardversion wird gesetzt

### Codec
- DTO wird korrekt in Envelope umgewandelt
- `instanceUuid` wird aus `data` entfernt
- Decode ergänzt `instanceUuid` korrekt wieder
- unbekannter Tag schlägt fehl

### Doctrine Type
- null roundtrip
- DTO roundtrip
- ungültiger PHP-Wert schlägt fehl
- ungültiges DB-Payload schlägt fehl

## Integrationstests

- Symfony Container bootet Bundle korrekt
- Doctrine Type wird registriert
- Entity mit `dto_json` kann persistiert und geladen werden
- polymorphe DTOs werden korrekt roundtripped
- TypedFieldMapper setzt den Type korrekt

## Kompatibilitätstests

Zielmatrix:
- PHP 8.2+
- Symfony 6.4 / 7.x
- Doctrine ORM 3.x
- Doctrine DBAL 4.x, sofern angestrebt

---

## Migrations- und Evolutionsstrategie

Für v1 nur vorbereiten, nicht voll implementieren.

### Vorsehen
- `version` im Envelope speichern
- Hook für spätere Payload-Upcaster definieren

### Mögliche v2-Komponente

```php
interface DtoJsonPayloadUpcasterInterface
{
    public function supports(string $tag, int $fromVersion): bool;

    /** @param array<string, mixed> $payload */
    public function upcast(array $payload): array;
}
```

---

## Sicherheits- und Robustheitsregeln

- niemals FQCN aus JSON instantiieren
- niemals unbekannte Tags stillschweigend ignorieren
- nur Klassen aus Registry erlauben
- Envelope strikt validieren
- technische Felder reservieren: `_dto`
- DTO-Nutzdaten dürfen `_dto` nicht überschreiben

---

## Umsetzungsreihenfolge

## Phase 1: Kernmodell
1. `AbstractEntityFieldDto`
2. `EntityFieldDtoType` Attribut
3. Exceptions
4. `DtoJsonEnvelope`
5. `DtoJsonEnvelopeFactory`

## Phase 2: Registry
6. `EntityFieldDtoRegistryInterface`
7. `EntityFieldDtoRegistry`
8. Compiler Pass zur Registrierung und Validierung

## Phase 3: Codec
9. `DtoJsonCodecInterface`
10. `DtoJsonCodec`
11. Serializer-Integration

## Phase 4: Doctrine
12. `DtoJsonType`
13. Bundle-Extension und Type-Registrierung
14. Integrationstest mit echter Entity

## Phase 5: Komfortfunktionen
15. `EntityFieldDtoTypedFieldMapper`
16. Bundle-Konfiguration für Auto-Mapping
17. Dokumentation und Beispielprojekt

---

## Akzeptanzkriterien

Das Bundle ist fertig, wenn:

- eine Symfony-App das Bundle per Composer einbinden kann
- `dto_json` als Doctrine-Type registriert ist
- ein Entity-Feld DTOs in JSON persistieren kann
- beim Laden das konkrete DTO über Registry-Tag korrekt wiederhergestellt wird
- `instanceUuid` korrekt roundtripped
- Änderungen durch neue DTO-Instanzen mit neuer UUID erfolgen
- keine FQCNs im JSON landen
- ungültige Payloads sauber mit Exceptions abbrechen
- mindestens ein End-to-End-Integrationstest grün ist

---

## Offene Entscheidungen

Diese Punkte soll der implementierende Agent dokumentiert entscheiden:

1. Wie werden DTOs im Container registriert?
   - als Symfony-Services im Host-Projekt
   - bevorzugt per Autoconfiguration aus `#[EntityFieldDtoType]`
   - alternativ per explizitem Service-Tag

2. Soll `type: dto_json` verpflichtend bleiben oder per TypedFieldMapper automatisch gesetzt werden?

3. Soll das Bundle eigene Maker-/Scaffolding-Kommandos liefern?

4. Soll `version` im Envelope sofort konfigurierbar sein oder in v1 fest auf `1` stehen?

5. Soll es zusätzlich ein Interface neben der Basisklasse geben, z. B. für flexiblere Typprüfungen?

---

## Kurzfazit

Die empfohlene Lösung ist ein klar geschichtetes Symfony-Bundle mit:
- abstrakter DTO-Basis
- Tag-basiertem Registry-Modell
- technischem JSON-Envelope
- Serializer-basiertem Codec
- zustandslosem Doctrine-DBAL-Type `dto_json`
- optionalem TypedFieldMapper für ergonomische Feldzuordnung

Die Identität einzelner DTO-Versionen wird über `instanceUuid` als String abgebildet. Jede inhaltliche Änderung erzeugt ein neues DTO mit neuer UUID. Die konkrete DTO-Klasse wird ausschließlich über die Registry bestimmt.
