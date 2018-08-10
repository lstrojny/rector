<?php declare(strict_types=1);

namespace Rector\NodeAnalyzer;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use Rector\NodeTypeResolver\Node\MetadataAttribute;

/**
 * Read-only utils for MethodCall Node:
 * $this->"someMethod()"
 */
final class MethodNameAnalyzer
{
    /**
     * @param string[] $types
     */
    public function isOverrideOfTypes(Node $node, array $types): bool
    {
        if (! $node instanceof Identifier) {
            return false;
        }

        $parentClassName = $node->getAttribute(MetadataAttribute::PARENT_CLASS_NAME);
        if (! $parentClassName) {
            return false;
        }

        return in_array($parentClassName, $types, true);
    }
}
