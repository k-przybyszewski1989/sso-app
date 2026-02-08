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

        foreach ($classReflection->getNativeReflection()->getReflectionConstants() as $constant) {
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
