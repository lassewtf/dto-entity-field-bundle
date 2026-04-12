<?php

declare(strict_types=1);

namespace Nano\DtoJsonEntityFieldBundle\Registry;

use Nano\DtoJsonEntityFieldBundle\Dto\AbstractEntityFieldDto;
use Nano\DtoJsonEntityFieldBundle\Exception\DtoJsonConfigurationException;
use Nano\DtoJsonEntityFieldBundle\Exception\UnknownDtoTagException;
use Nano\DtoJsonEntityFieldBundle\Exception\UnsupportedDtoClassException;

final readonly class EntityFieldDtoRegistry implements EntityFieldDtoRegistryInterface
{
    /**
     * @var array<string, class-string<AbstractEntityFieldDto>>
     */
    private array $tagToClass;

    /**
     * @var array<class-string<AbstractEntityFieldDto>, string>
     */
    private array $classToTag;

    /**
     * @param array<string, class-string<AbstractEntityFieldDto>> $tagToClass
     */
    public function __construct(array $tagToClass)
    {
        $classToTag = [];

        foreach ($tagToClass as $tag => $class) {
            if ('' === $tag) {
                throw new DtoJsonConfigurationException('Registered DTO tags must not be empty.');
            }

            if (!class_exists($class)) {
                throw new DtoJsonConfigurationException(\sprintf('Registered DTO class "%s" does not exist.', $class));
            }

            if (!is_a($class, AbstractEntityFieldDto::class, true)) {
                throw new DtoJsonConfigurationException(\sprintf(
                    'Registered DTO class "%s" must extend "%s".',
                    $class,
                    AbstractEntityFieldDto::class,
                ));
            }

            if (isset($classToTag[$class])) {
                throw new DtoJsonConfigurationException(\sprintf(
                    'DTO class "%s" is already registered for tag "%s".',
                    $class,
                    $classToTag[$class],
                ));
            }

            $classToTag[$class] = $tag;
        }

        $this->tagToClass = $tagToClass;
        $this->classToTag = $classToTag;
    }

    public function tagFor(string $class): string
    {
        return $this->classToTag[$class] ?? throw new UnsupportedDtoClassException($class);
    }

    public function classForTag(string $tag): string
    {
        return $this->tagToClass[$tag] ?? throw new UnknownDtoTagException($tag);
    }

    public function isSupported(string $class): bool
    {
        return isset($this->classToTag[$class]);
    }
}
