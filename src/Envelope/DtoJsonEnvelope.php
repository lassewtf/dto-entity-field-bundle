<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Envelope;

final readonly class DtoJsonEnvelope
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $tag,
        public string $instanceUuid,
        public int $version,
        public array $data,
    ) {
    }

    /**
     * @return array{_dto: array{tag: string, instanceUuid: string, version: int}, data: array<string, mixed>}
     */
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
