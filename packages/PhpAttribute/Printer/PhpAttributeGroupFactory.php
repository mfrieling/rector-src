<?php

declare(strict_types=1);

namespace Rector\PhpAttribute\Printer;

use PhpParser\BuilderHelpers;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\PhpAttribute\NodeAnalyzer\NamedArgumentsResolver;
use Rector\PhpAttribute\Value\ValueNormalizer;
use Webmozart\Assert\Assert;

/**
 * @see \Rector\Tests\PhpAttribute\Printer\PhpAttributeGroupFactoryTest
 */
final class PhpAttributeGroupFactory
{
    public function __construct(
        private NamedArgumentsResolver $namedArgumentsResolver,
        private ValueNormalizer $valueNormalizer
    ) {
    }

    public function createFromSimpleTag(AnnotationToAttribute $annotationToAttribute): AttributeGroup
    {
        return $this->createFromClass($annotationToAttribute->getAttributeClass());
    }

    public function createFromClass(string $attributeClass): AttributeGroup
    {
        $fullyQualified = new FullyQualified($attributeClass);
        $attribute = new Attribute($fullyQualified);
        return new AttributeGroup([$attribute]);
    }

    /**
     * @param mixed[] $items
     */
    public function createFromClassWithItems(string $attributeClass, array $items): AttributeGroup
    {
        $fullyQualified = new FullyQualified($attributeClass);
        $args = $this->createArgsFromItems($items);
        $attribute = new Attribute($fullyQualified, $args);

        return new AttributeGroup([$attribute]);
    }

    public function create(
        DoctrineAnnotationTagValueNode $doctrineAnnotationTagValueNode,
        AnnotationToAttribute $annotationToAttribute,
    ): AttributeGroup {
        $values = $doctrineAnnotationTagValueNode->getValuesWithExplicitSilentAndWithoutQuotes();

        $args = $this->createArgsFromItems($values);
        $argumentNames = $this->namedArgumentsResolver->resolveFromClass($annotationToAttribute->getAttributeClass());

        $this->completeNamedArguments($args, $argumentNames);

        $attributeName = $this->createAttributeName($annotationToAttribute, $doctrineAnnotationTagValueNode);

        $attribute = new Attribute($attributeName, $args);
        return new AttributeGroup([$attribute]);
    }

    /**
     * @param mixed[] $items
     * @return Arg[]
     */
    public function createArgsFromItems(array $items, ?string $silentKey = null): array
    {
        $args = [];
        if ($silentKey !== null && isset($items[$silentKey])) {
            $silentValue = $this->mapAnnotationValueToAttribute($items[$silentKey]);

            $args[] = new Arg($silentValue);
            unset($items[$silentKey]);
        }

        foreach ($items as $key => $value) {
            $value = $this->mapAnnotationValueToAttribute($value);

            $name = null;
            if (is_string($key)) {
                $name = new Identifier($key);
            }

            // resolve argument name
            $args[] = $this->isArrayArguments($items)
                ? new Arg($value, false, false, [], $name)
                : new Arg($value)
                ;
        }

        return $args;
    }

    /**
     * @param mixed[] $items
     */
    private function isArrayArguments(array $items): bool
    {
        foreach (array_keys($items) as $key) {
            if (! is_int($key)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeStringDoubleQuote(Expr $expr): void
    {
        if (! $expr instanceof String_) {
            return;
        }

        // avoid escaping quotes + preserve newlines
        if (! str_contains($expr->value, "'")) {
            return;
        }

        if (str_contains($expr->value, "\n")) {
            return;
        }

        $expr->setAttribute(AttributeKey::KIND, String_::KIND_DOUBLE_QUOTED);
    }

    /**
     * @param Arg[] $args
     * @param string[] $argumentNames
     */
    private function completeNamedArguments(array $args, array $argumentNames): void
    {
        Assert::allIsAOf($args, Arg::class);

        foreach ($args as $key => $arg) {
            $argumentName = $argumentNames[$key] ?? null;
            if ($argumentName === null) {
                continue;
            }

            if ($arg->name !== null) {
                continue;
            }

            $arg->name = new Identifier($argumentName);
        }
    }

    private function mapAnnotationValueToAttribute(mixed $annotationValue): Expr
    {
        $value = $this->valueNormalizer->normalize($annotationValue);
        $value = BuilderHelpers::normalizeValue($value);
        $this->normalizeStringDoubleQuote($value);

        return $value;
    }

    private function createAttributeName(
        AnnotationToAttribute $annotationToAttribute,
        DoctrineAnnotationTagValueNode $doctrineAnnotationTagValueNode
    ): FullyQualified|Name {
        // attribute and class name are the same, so we re-use the short form to keep code compatible with previous one
        if ($annotationToAttribute->getAttributeClass() === $annotationToAttribute->getTag()) {
            $attributeName = $doctrineAnnotationTagValueNode->identifierTypeNode->name;
            $attributeName = ltrim($attributeName, '@');
            return new Name($attributeName);
        }

        return new FullyQualified($annotationToAttribute->getAttributeClass());
    }
}
