<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Envelope;

use Nano\DtoJsonEntityFieldBundle\Exception\InvalidDtoPayloadException;

final readonly class DtoJsonEnvelopeFactory
{
    public function __construct(
        private int $version = 1,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(string $tag, string $instanceUuid, array $data): DtoJsonEnvelope
    {
        if ('' === $tag) {
            throw new InvalidDtoPayloadException('Envelope tag must not be empty.');
        }

        if ('' === $instanceUuid) {
            throw new InvalidDtoPayloadException('Envelope instanceUuid must not be empty.');
        }

        if (\array_key_exists('_dto', $data)) {
            throw new InvalidDtoPayloadException('The "_dto" key is reserved for envelope metadata.');
        }

        return new DtoJsonEnvelope($tag, $instanceUuid, $this->version, $data);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function fromArray(array $payload): DtoJsonEnvelope
    {
        $metadata = $payload['_dto'] ?? null;
        if (!\is_array($metadata)) {
            throw new InvalidDtoPayloadException('Payload must contain an object-like "_dto" section.');
        }

        $tag = $metadata['tag'] ?? null;
        if (!\is_string($tag) || '' === $tag) {
            throw new InvalidDtoPayloadException('Payload "_dto.tag" must be a non-empty string.');
        }

        $instanceUuid = $metadata['instanceUuid'] ?? null;
        if (!\is_string($instanceUuid) || '' === $instanceUuid) {
            throw new InvalidDtoPayloadException('Payload "_dto.instanceUuid" must be a non-empty string.');
        }

        $version = $metadata['version'] ?? $this->version;
        if (!\is_int($version) || $version < 1) {
            throw new InvalidDtoPayloadException('Payload "_dto.version" must be a positive integer.');
        }

        $data = $payload['data'] ?? null;
        if (!\is_array($data)) {
            throw new InvalidDtoPayloadException('Payload "data" must be an array.');
        }

        return new DtoJsonEnvelope($tag, $instanceUuid, $version, $data);
    }
}
