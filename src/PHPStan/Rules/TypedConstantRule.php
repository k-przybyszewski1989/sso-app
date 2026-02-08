<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Rule<InClassNode>
 */
final class TypedConstantRule implements Rule
{
    /**
     * {@inheritDoc}
     */
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * {@inheritDoc}
     *
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];
        $classReflection = $node->getClassReflection();
        $nativeReflection = $classReflection->getNativeReflection();

        // Skip enums - their cases are typed by the enum's backing type
        if ($nativeReflection->isEnum()) {
            return [];
        }

        foreach ($nativeReflection->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $classReflection->getName()) {
                continue;
            }

            if (!$constant->hasType()) {
                $errors[] = RuleErrorBuilder::message(
                    sprintf(
                        'Constant %s::%s is missing a type declaration.',
                        $classReflection->getName(),
                        $constant->getName()
                    )
                )->identifier('constant.missingType')->build();
            }
        }

        return $errors;
    }
}
