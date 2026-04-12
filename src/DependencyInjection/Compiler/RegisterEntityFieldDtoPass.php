<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\DependencyInjection\Compiler;

use Nano\DtoJsonEntityFieldBundle\Attribute\EntityFieldDtoType;
use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;
use Nano\DtoJsonEntityFieldBundle\Exception\DtoJsonConfigurationException;
use Nano\DtoJsonEntityFieldBundle\NanoDtoJsonEntityFieldBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RegisterEntityFieldDtoPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $tagToClass = [];
        $classToTag = [];

        /** @var array<string, string> $configuredMappings */
        $configuredMappings = $container->hasParameter('nano_dto_json_entity_field.registry.mappings')
            ? $container->getParameter('nano_dto_json_entity_field.registry.mappings')
            : [];

        foreach ($configuredMappings as $tag => $class) {
            $resolvedClass = $this->resolveClass($container, $class);
            $this->registerMapping($tagToClass, $classToTag, $this->validateExplicitMapping($tag, $resolvedClass));
        }

        foreach ($container->findTaggedServiceIds(NanoDtoJsonEntityFieldBundle::DTO_SERVICE_TAG) as $serviceId => $_tags) {
            $definition = $container->findDefinition($serviceId);
            if ($definition->isAbstract()) {
                continue;
            }

            $class = $definition->getClass() ?? $serviceId;
            $resolvedClass = $this->resolveClass($container, $class);
            $this->registerMapping($tagToClass, $classToTag, $this->attributeMappingFor($resolvedClass));
        }

        $container->setParameter('nano_dto_json_entity_field.registry.mappings', $tagToClass);
    }

    /**
     * @return array{tag: string, class: class-string<AbstractEntityFieldDto>}
     */
    private function validateExplicitMapping(string $tag, string $class): array
    {
        if ('' === $tag) {
            throw new DtoJsonConfigurationException('Configured DTO tags must not be empty.');
        }

        $this->assertSupportedDtoClass($class);

        $attribute = $this->readAttribute($class);
        if (null !== $attribute && $attribute->tag !== $tag) {
            throw new DtoJsonConfigurationException(\sprintf(
                'Configured tag "%s" does not match attribute tag "%s" on DTO "%s".',
                $tag,
                $attribute->tag,
                $class,
            ));
        }

        /** @var class-string<AbstractEntityFieldDto> $class */
        return ['tag' => $tag, 'class' => $class];
    }

    /**
     * @return array{tag: string, class: class-string<AbstractEntityFieldDto>}
     */
    private function attributeMappingFor(string $class): array
    {
        $this->assertSupportedDtoClass($class);
        $attribute = $this->readAttribute($class);

        if (null === $attribute) {
            throw new DtoJsonConfigurationException(\sprintf(
                'Tagged DTO service "%s" must declare the #[%s] attribute.',
                $class,
                EntityFieldDtoType::class,
            ));
        }

        /** @var class-string<AbstractEntityFieldDto> $class */
        return ['tag' => $attribute->tag, 'class' => $class];
    }

    /**
     * @param array<string, class-string<AbstractEntityFieldDto>> $tagToClass
     * @param array<class-string<AbstractEntityFieldDto>, string> $classToTag
     * @param array{tag: string, class: class-string<AbstractEntityFieldDto>} $mapping
     */
    private function registerMapping(array &$tagToClass, array &$classToTag, array $mapping): void
    {
        $existingClass = $tagToClass[$mapping['tag']] ?? null;
        if (null !== $existingClass && $existingClass !== $mapping['class']) {
            throw new DtoJsonConfigurationException(\sprintf(
                'Duplicate DTO tag "%s" for classes "%s" and "%s".',
                $mapping['tag'],
                $existingClass,
                $mapping['class'],
            ));
        }

        $existingTag = $classToTag[$mapping['class']] ?? null;
        if (null !== $existingTag && $existingTag !== $mapping['tag']) {
            throw new DtoJsonConfigurationException(\sprintf(
                'DTO class "%s" is already registered for tag "%s".',
                $mapping['class'],
                $existingTag,
            ));
        }

        $tagToClass[$mapping['tag']] = $mapping['class'];
        $classToTag[$mapping['class']] = $mapping['tag'];
    }

    private function resolveClass(ContainerBuilder $container, string $class): string
    {
        $resolved = $container->getParameterBag()->resolveValue($class);
        if (!\is_string($resolved) || '' === $resolved) {
            throw new DtoJsonConfigurationException('DTO class definitions must resolve to a non-empty class string.');
        }

        return $resolved;
    }

    private function assertSupportedDtoClass(string $class): void
    {
        if (!class_exists($class)) {
            throw new DtoJsonConfigurationException(\sprintf('Configured DTO class "%s" does not exist.', $class));
        }

        if (!is_a($class, AbstractEntityFieldDto::class, true)) {
            throw new DtoJsonConfigurationException(\sprintf(
                'DTO class "%s" must extend "%s".',
                $class,
                AbstractEntityFieldDto::class,
            ));
        }
    }

    private function readAttribute(string $class): ?EntityFieldDtoType
    {
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(EntityFieldDtoType::class);

        if ([] === $attributes) {
            return null;
        }

        if (\count($attributes) > 1) {
            throw new DtoJsonConfigurationException(\sprintf(
                'DTO class "%s" must declare at most one #[%s] attribute.',
                $class,
                EntityFieldDtoType::class,
            ));
        }

        return $attributes[0]->newInstance();
    }
}
