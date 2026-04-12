# Agent Brief: Symfony Bundle für generisches `dto_json`-Doctrine-Field

## Ziel

Erstelle ein **Symfony Bundle**, das in beliebige Symfony-Projekte integriert werden kann und ein generisches Doctrine-ORM-Feld `dto_json` bereitstellt.

Das Bundle soll ermöglichen, dass ein Doctrine-Entity-Feld in der Datenbank als **JSON** gespeichert wird, in PHP aber als **DTO-Objekt** verfügbar ist.

Das Feld muss **polymorph** sein:
- mehrere konkrete DTO-Klassen müssen unterstützt werden,
- alle DTOs müssen von einer gemeinsamen abstrakten Basisklasse erben,
- die konkrete DTO-Klasse wird **nicht** über einen Serializer-Discriminator bestimmt,
- sondern über einen **gespeicherten DTO-Tag** und eine **Registry**.

Das Ergebnis soll ein installierbares Bundle sein, das ich später per Composer in mehrere Symfony-Projekte einbinden kann.

---

## Fachliche Regeln

### 1. DTO-Basisklasse

Alle DTOs, die in einem `dto_json`-Feld gespeichert werden dürfen, **müssen** von einer gemeinsamen abstrakten Basisklasse erben:

```php
abstract class AbstractEntityFieldDto
{
    public function __construct(
        public readonly string $instanceUuid,
    ) {}
}
```

### 2. `instanceUuid`

Regeln für `instanceUuid`:
- Typ immer `string`
- beim Laden aus der Datenbank wird die gespeicherte UUID übernommen
- bei **jeder inhaltlichen Änderung** eines DTOs muss eine **neue UUID** erzeugt werden
- DTOs dürfen **nicht in place mutiert** werden
- Änderungen erfolgen nur über neue DTO-Instanzen

### 3. Typauflösung

Die Typauflösung darf **nicht** auf Symfony-Serializer-Discriminator-Metadaten basieren.

Stattdessen gilt:
- jede konkrete DTO-Klasse besitzt einen **eindeutigen Tag**
- im JSON wird dieser Tag gespeichert
- beim Laden wird der Tag über eine **Registry** in eine konkrete Klasse aufgelöst

### 4. JSON-Envelope

Das Persistenzformat soll ein technisches Envelope-Format sein.

Beispiel:

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

### 5. Keine FQCNs im JSON

Im JSON darf **kein PHP-FQCN** gespeichert werden.

Nur der DTO-Tag darf gespeichert werden.

---

## Technische Ziele

Das Bundle soll folgende Kernfunktionen liefern:

1. **Doctrine DBAL Type** `dto_json`
2. **abstrakte Basisklasse** für Entity-Field-DTOS
3. **Registry** zur Auflösung `tag <-> class`
4. **Symfony-Serializer-basierte** Normalisierung/Denormalisierung
5. **Attribute** zum Taggen konkreter DTO-Klassen
6. **automatische oder einfache Doctrine-Registrierung**
7. optional: **TypedFieldMapper** zur automatischen Typzuordnung wie bei typisierten Feldern
8. gute Fehlerbehandlung bei ungültigem JSON / unbekannten Tags / ungültigen DTO-Klassen

---

## Architekturvorgaben

## A. Bundle-Struktur

Erzeuge ein Bundle mit sauberer öffentlicher API, z. B. in dieser Form:

```text
src/
  DtoJsonBundle.php
  Attribute/
    EntityFieldDtoType.php
  Doctrine/
    Type/
      DtoJsonType.php
      DtoJsonTypeRuntime.php
    TypedFieldMapper/
      EntityFieldDtoTypedFieldMapper.php
  Dto/
    AbstractEntityFieldDto.php
  Registry/
    EntityFieldDtoRegistryInterface.php
    EntityFieldDtoRegistry.php
  Codec/
    DtoJsonCodecInterface.php
    DtoJsonCodec.php
  Envelope/
    DtoJsonEnvelope.php
    DtoJsonEnvelopeFactory.php
  DependencyInjection/
    DtoJsonExtension.php
    Compiler/
      RegisterEntityFieldDtoPass.php
  Exception/
    UnknownDtoTagException.php
    InvalidDtoPayloadException.php
    UnsupportedDtoClassException.php
  Resources/
    config/
      services.php
```

Diese Struktur ist eine Empfehlung. Anpassungen sind erlaubt, aber die Verantwortlichkeiten müssen klar getrennt bleiben.

---

## B. Öffentliche API des Bundles

Das Bundle soll mindestens diese öffentlichen Bausteine bereitstellen:

### 1. Abstrakte Basisklasse

```php
abstract class AbstractEntityFieldDto
{
    public function __construct(
        public readonly string $instanceUuid,
    ) {}
}
```

### 2. DTO-Tag-Attribut

Implementiere ein Klassen-Attribut, mit dem konkrete DTOs ihren Tag deklarieren:

```php
#[\Attribute(\Attribute::TARGET_CLASS)]
final class EntityFieldDtoType
{
    public function __construct(
        public readonly string $tag,
    ) {}
}
```

Beispielnutzung:

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
}
```

### 3. Registry Interface

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

### 4. Codec Interface

```php
interface DtoJsonCodecInterface
{
    public function encode(AbstractEntityFieldDto $dto): array;

    public function decode(array $payload): AbstractEntityFieldDto;
}
```

---

## C. Registry-Konzept

Die Registry ist zentral.

Pflichten der Registry:
- `tag -> class` auflösen
- `class -> tag` auflösen
- sicherstellen, dass nur Klassen registriert werden, die von `AbstractEntityFieldDto` erben
- doppelte Tags verhindern
- doppelte Klassen verhindern
- unbekannte Tags mit klaren Exceptions behandeln

### Registrierungsmechanismus

Bevorzugt:
- DTO-Klassen im Host-Projekt werden als Symfony-Services geladen
- das Attribut `EntityFieldDtoType` liefert den Tag auf der DTO-Klasse
- das Bundle stellt Autoconfiguration bereit, sodass Klassen mit diesem Attribut automatisch als DTO-Service getaggt werden können
- ein Compiler Pass sammelt diese Container-bekannten DTO-Services, validiert sie und baut daraus die Registry

Akzeptable Alternative:
- explizite Bundle-Konfiguration, in der Tag/Class-Mappings direkt eingetragen werden, falls ein Host-Projekt DTOs bewusst nicht als Services lädt

Bevorzugte Lösung:
- **Attribut auf der DTO-Klasse** + **Service-Ladung im Host-Projekt** + **Autoconfiguration/Service-Tagging** + **Compiler Pass**

Wichtige Präzisierung:
- ein Compiler Pass sieht nur Container-Definitionen
- deshalb müssen DTO-Klassen, die automatisch registriert werden sollen, im Host-Projekt als Services geladen werden
- das Bundle darf nicht behaupten, beliebige DTO-Klassen außerhalb des Containers automatisch zu finden

---

## D. Envelope-Modell

Implementiere ein internes Envelope-Modell.

Vorgaben:
- Meta-Informationen und Fachdaten strikt trennen
- Meta-Daten liegen unter `_dto`
- Fachdaten liegen unter `data`

### Envelope-Felder

Pflichtfelder:
- `_dto.tag` (`string`)
- `_dto.instanceUuid` (`string`)
- `_dto.version` (`int`, initial `1`)
- `data` (`array`)

Implementiere eine kleine Value-Struktur oder Factory, die dieses Envelope validiert und erzeugt.

---

## E. Serialisierung / Denormalisierung

Nutze den **Symfony Serializer**.

### Encode-Regeln

Beim Persist:
1. DTO muss Instanz von `AbstractEntityFieldDto` sein
2. Registry liefert den Tag für die konkrete Klasse
3. DTO wird mit Symfony Serializer normalisiert
4. technische Meta-Felder dürfen nicht unkontrolliert in `data` landen
5. `instanceUuid` wird aus dem DTO gelesen
6. Envelope wird gebaut
7. Envelope wird als JSON gespeichert

### Decode-Regeln

Beim Laden:
1. JSON wird zu einem Array decodiert
2. Envelope wird validiert
3. Tag wird gelesen
4. Registry liefert die konkrete DTO-Klasse
5. `instanceUuid` wird in die Denormalisierungsdaten aufgenommen
6. Symfony Serializer denormalisiert in die konkrete Klasse
7. Rückgabe ist eine Instanz von `AbstractEntityFieldDto`

### Wichtige Vorgabe

**Nicht** auf Symfony-Discriminator-Mapping aufbauen.

Die Auswahl der konkreten Klasse erfolgt ausschließlich über die Registry.

### `object_to_populate`

Nicht als Primärmechanik verwenden.

Begründung im Design berücksichtigen:
- DTOs sind immutable
- Änderungen führen zu neuen Instanzen
- `object_to_populate` kann später optional unterstützt werden, ist aber **nicht Teil von V1**

---

## F. Doctrine DBAL Type `dto_json`

Implementiere einen generischen DBAL-Type `dto_json`.

Verantwortlichkeiten:
- `null` korrekt behandeln
- beim Persist DTO -> Array-Envelope -> JSON
- beim Load JSON -> Array-Envelope -> DTO
- saubere Exceptions bei ungültigen Werten

Wichtig:
- keine feldspezifische Laufzeitkonfiguration im Type speichern
- Type als generischen, wiederverwendbaren Type umsetzen
- keine Logik abhängig von einer konkreten Entity oder Property
- der Type muss mit der üblichen Doctrine-Type-Registrierung per Klassenname funktionieren
- der Type darf nicht voraussetzen, dass Doctrine ihn als normalen Symfony-Service mit Konstruktor-Abhängigkeiten instanziiert

Architekturhinweis:
- der `DtoJsonType` bleibt zustandslos
- `Codec`, `Registry` und `EnvelopeFactory` bleiben normale Symfony-Services
- falls der Type auf diese Services zugreifen muss, erfolgt das über eine kleine interne Runtime-Bridge oder eine äquivalente zustandslose Anbindung
- Konstruktor-Injection von `Codec` in den Doctrine-Type ist für V1 nicht die bevorzugte Lösung

### Erwartetes Verhalten

Entity-Beispiel:

```php
#[ORM\Entity]
final class Order
{
    #[ORM\Column(type: 'dto_json', nullable: true)]
    private ?AbstractEntityFieldDto $payload = null;
}
```

---

## G. TypedFieldMapper

Prüfe die Umsetzung eines optionalen `TypedFieldMapper`.

Ziel:
- wenn ein Doctrine-Feld auf `AbstractEntityFieldDto` oder eine Unterklasse typisiert ist,
- soll automatisch `type = dto_json` gesetzt werden können

Anforderungen:
- nur aktiv werden, wenn das Mapping nicht bereits explizit gesetzt ist
- keine unerwartete Überschreibung expliziter Doctrine-Mappings
- sauber dokumentieren, ob dieser Mapper standardmäßig aktiv ist oder opt-in

Falls die automatische Zuordnung in V1 zu fragil ist, implementiere sie als **optionales Feature**.

---

## H. Fehlerbehandlung

Implementiere klare, projekttaugliche Exceptions.

Pflichtfälle:
- JSON ist kein gültiges Objekt
- `_dto` fehlt
- `tag` fehlt
- `instanceUuid` fehlt oder ist kein String
- `data` fehlt oder ist kein Array
- unbekannter DTO-Tag
- DTO-Klasse ist nicht unterstützt
- DTO-Klasse erbt nicht von `AbstractEntityFieldDto`
- Serializer kann Objekt nicht erzeugen

Fehler müssen klar und gut debugbar sein.

---

## I. Änderungsmodell der DTOs

Das Bundle soll das folgende Modell unterstützen und dokumentieren:

- DTOs sind immutable
- keine Setter-Pflicht, besser reine Konstruktoren / `with*()`-Methoden
- bei jeder inhaltlichen Änderung wird eine neue DTO-Instanz mit neuer `instanceUuid` gebaut
- beim Hydratisieren aus der DB wird die bestehende `instanceUuid` übernommen

### Erwartung an Nutzer des Bundles

Beispiel:

```php
final class ProductAddedDto extends AbstractEntityFieldDto
{
    public function withQuantity(int $quantity): self
    {
        return new self(
            instanceUuid: 'NEUE_UUID',
            sku: $this->sku,
            quantity: $quantity,
        );
    }
}
```

Das Bundle muss **keine UUID-Erzeugung erzwingen**, darf dafür aber optional einen Helper anbieten.

Optional:
- `EntityFieldDtoUuidGeneratorInterface`
- Standardimplementierung mit Symfony UID oder Ramsey UUID

---

## J. Konfiguration des Bundles

Das Bundle soll in Symfony-Projekten einfach integrierbar sein.

### Ziel

Nach Installation per Composer soll folgendes möglich sein:

1. Bundle registrieren
2. Services automatisch laden
3. Doctrine Type registrieren
4. DTO-Klassen taggen
5. direkt in Entities nutzen

### Bundle-Konfiguration

Erwäge eine Konfiguration etwa dieser Form:

```yaml
entity_field_dto:
  doctrine:
    auto_register_type: true
    enable_typed_field_mapper: false
  serializer:
    format: json
  envelope:
    version: 1
```

Regel für V1:
- das Attribut `#[EntityFieldDtoType]` bleibt die Quelle des Tags auf der Klasse
- DTO-Klassen im Host-Projekt müssen durch die normale Symfony-Service-Ladung in den Container gelangen
- das Bundle kann per Autoconfiguration Klassen mit `#[EntityFieldDtoType]` automatisch mit einem DTO-Service-Tag versehen
- der Compiler Pass baut die Registry aus diesen getaggten DTO-Services

Beispiel im Host-Projekt:

```yaml
services:
  App\:
    resource: '../src/'
    autowire: true
    autoconfigure: true
```

Wenn das Host-Projekt DTO-Klassen nicht als Services lädt, ist eine explizite Fallback-Konfiguration akzeptabel.

Konfiguration darf minimal bleiben, muss aber erweiterbar entworfen sein.

---

## K. Dokumentation / DX

Das Bundle soll entwicklerfreundlich sein.

Bitte liefere mindestens:

### 1. README

Mit:
- Problemstellung
- Installationsanleitung
- Bundle-Registrierung
- Doctrine-Registrierung
- Beispiel-DTO
- Beispiel-Entity
- Beispiel-JSON in der DB
- Erklärung der `instanceUuid`-Regel
- Erklärung der Registry/Tag-Auflösung

### 2. Integrationsbeispiel

Beispielprojekt oder `/docs`-Kapitel mit:
- konkretem DTO
- Entity mit `dto_json`
- Persist + Reload Ablauf

### 3. Architekturhinweise

Kurz dokumentieren:
- warum keine DiscriminatorMap verwendet wird
- warum kein FQCN im JSON liegt
- warum DTOs immutable sein sollen
- warum `instanceUuid` bei Änderungen neu ist

---

## L. Tests

Pflicht: automatisierte Tests.

### Unit-Tests

Für:
- Registry
- Codec
- Envelope-Validierung
- Exceptions

### Integrationstests

Für:
- Doctrine `dto_json` Type
- Persist + Load mit SQLite/PostgreSQL-kompatibler JSON-Nutzung, soweit praktikabel
- korrektes Roundtrip-Verhalten
- unbekannter Tag beim Laden
- falsches JSON-Format
- DTO mit geänderter UUID

### Erwartete Testfälle

1. DTO wird gespeichert und korrekt geladen
2. Tag wird korrekt auf Klasse gemappt
3. UUID wird aus DB übernommen
4. geändertes DTO mit neuer UUID wird als neue JSON-Payload gespeichert
5. ungültige Payload schlägt klar fehl
6. nicht registrierte DTO-Klasse schlägt beim Persist klar fehl

---

## M. Nicht-Ziele für V1

Folgende Dinge **nicht** in V1 überkomplex machen:

- keine Serializer-Discriminator-Strategie
- kein Speichern von FQCNs im JSON
- keine automatische Deep-Mutation bestehender DTO-Objekte
- keine Feld-spezifische DBAL-Type-Konfiguration mit Zustand im Type
- kein Versuch, Doctrine Dirty Checking durch interne Magie zu ersetzen

---

## N. Erwartete Ergebnisartefakte

Der Agent soll mindestens liefern:

1. vollständiges Symfony Bundle
2. Composer-konforme Paketstruktur
3. README
4. Tests
5. Beispielcode zur Integration in ein Host-Projekt
6. klare öffentliche API

---

## O. Implementierungspriorität

In dieser Reihenfolge umsetzen:

### Phase 1
- Bundle-Skelett
- `AbstractEntityFieldDto`
- `EntityFieldDtoType` Attribut
- Registry
- Envelope
- Codec
- DBAL Type `dto_json`

### Phase 2
- Bundle-Konfiguration
- automatische Registrierung
- Compiler Pass
- Service-Tagging

### Phase 3
- optionaler TypedFieldMapper
- zusätzliche DX-Verbesserungen
- optionale UUID-Helfer

### Phase 4
- Dokumentation
- Tests härten
- Beispielprojekt

---

## P. Qualitätsanforderungen

Der Code soll:
- Symfony-idiomatisch sein
- strikt typisiert sein
- gute Exceptions verwenden
- geringe Kopplung zwischen Registry / Codec / Doctrine-Type aufweisen
- erweiterbar bleiben
- ohne projektinterne Annahmen funktionieren

Bevorzuge kleine, klar getrennte Klassen statt einer großen God-Class.

---

## Q. Entscheidungsvorgaben

Bei unklaren Stellen gelten diese Prioritäten:

1. **stabile Bundle-API** vor magischer Automatik
2. **explizite Registry** vor impliziter Heuristik
3. **immutability** vor Update-in-place
4. **lesbares JSON-Envelope** vor maximal kompakter Payload
5. **klare Fehlermeldungen** vor stiller Toleranz

---

## R. Akzeptanzkriterien

Die Aufgabe ist erfüllt, wenn:

- ich das Bundle in ein Symfony-Projekt installieren kann,
- ein DTO mit Tag registriert werden kann,
- ein Entity-Feld mit `dto_json` ein DTO als JSON speichert,
- beim Laden anhand des Tags wieder die richtige DTO-Klasse entsteht,
- `instanceUuid` korrekt mitgeführt wird,
- das Ganze ohne Serializer-Discriminator funktioniert,
- das Bundle dokumentiert und testbar ist.

---

## S. Optional sinnvolle Erweiterungen nach V1

Nur vorbereiten, nicht zwingend direkt implementieren:
- versionierte Payload-Upcaster
- optionaler UUID-Generator-Service
- Feldspezifische Base-Type-Einschränkung per Property-Attribut
- Debug-Command zur Anzeige registrierter DTO-Tags
- Maker-Command für neue DTO-Klassen

---

## Abschlussanweisung an den Agenten

Setze dies als **allgemein wiederverwendbares Symfony Bundle** um, nicht als projektspezifische Lösung.

Behalte die Trennung der Verantwortlichkeiten strikt bei:
- Registry löst Typen auf
- Codec baut/liest das Envelope
- Doctrine-Type macht DB-Konvertierung
- DTOs bleiben immutable
- `instanceUuid` bleibt stringbasiert und versionsgebunden an die konkrete DTO-Instanz

Wenn technische Entscheidungen offen sind, bevorzuge einfache, gut dokumentierte und wartbare Lösungen gegenüber cleverer Magie.
