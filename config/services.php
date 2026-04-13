<?php

declare(strict_types=1);

use Doctrine\ORM\Mapping\ChainTypedFieldMapper;
use Nano\DtoJsonEntityFieldBundle\Codec\DtoJsonCodec;
use Nano\DtoJsonEntityFieldBundle\Codec\DtoJsonCodecInterface;
use Nano\DtoJsonEntityFieldBundle\Doctrine\TypedFieldMapper\EntityFieldDtoTypedFieldMapper;
use Nano\DtoJsonEntityFieldBundle\Envelope\DtoJsonEnvelopeFactory;
use Nano\DtoJsonEntityFieldBundle\Registry\EntityFieldDtoRegistry;
use Nano\DtoJsonEntityFieldBundle\Registry\EntityFieldDtoRegistryInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->private();

    $services
        ->set('nano_dto_json_entity_field.envelope_factory', DtoJsonEnvelopeFactory::class)
        ->arg('$version', param('nano_dto_json_entity_field.envelope.version'));

    $services
        ->set('nano_dto_json_entity_field.registry', EntityFieldDtoRegistry::class)
        ->arg('$tagToClass', param('nano_dto_json_entity_field.registry.mappings'));

    $services->alias(EntityFieldDtoRegistryInterface::class, 'nano_dto_json_entity_field.registry');

    $services
        ->set('nano_dto_json_entity_field.codec', DtoJsonCodec::class)
        ->arg('$normalizer', service('serializer'))
        ->arg('$denormalizer', service('serializer'))
        ->arg('$registry', service(EntityFieldDtoRegistryInterface::class))
        ->arg('$envelopeFactory', service('nano_dto_json_entity_field.envelope_factory'));

    $services->alias(DtoJsonCodecInterface::class, 'nano_dto_json_entity_field.codec');
    $services->alias('nano_dto_json_entity_field.runtime_codec', 'nano_dto_json_entity_field.codec')->public();

    $services->set('nano_dto_json_entity_field.doctrine.typed_field_mapper', EntityFieldDtoTypedFieldMapper::class);

    $services
        ->set('nano_dto_json_entity_field.doctrine.chain_typed_field_mapper', ChainTypedFieldMapper::class)
        ->args([
            service('doctrine.orm.typed_field_mapper.default'),
            service('nano_dto_json_entity_field.doctrine.typed_field_mapper'),
        ]);
};
