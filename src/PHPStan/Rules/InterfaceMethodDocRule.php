<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<InClassMethodNode>
 */
final readonly class InterfaceMethodDocRule implements Rule
{
    /**
     * {@inheritDoc}
     */
    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * {@inheritDoc}
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (null === $classReflection) {
            return [];
        }

        $methodReflection = $scope->getFunction();
        if (!$methodReflection instanceof \PHPStan\Reflection\MethodReflection) {
            return [];
        }

        // Skip if method doesn't implement an interface method
        if (!$this->implementsInterfaceMethod($classReflection, $methodReflection->getName())) {
            return [];
        }

        $originalNode = $node->getOriginalNode();
        if (!$originalNode instanceof ClassMethod) {
            return [];
        }

        // Check if docblock contains {@inheritDoc}
        $docComment = $originalNode->getDocComment();
        if (null === $docComment) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Method %s::%s() implements an interface method but is missing {@inheritDoc} annotation.',
                    $classReflection->getName(),
                    $methodReflection->getName()
                ))
                ->identifier('missingInheritDoc')
                ->build(),
            ];
        }

        $docText = $docComment->getText();
        if (!str_contains($docText, '@inheritDoc') && !str_contains($docText, '{@inheritDoc}')) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Method %s::%s() implements an interface method but is missing {@inheritDoc} annotation.',
                    $classReflection->getName(),
                    $methodReflection->getName()
                ))
                ->identifier('missingInheritDoc')
                ->build(),
            ];
        }

        return [];
    }

    private function implementsInterfaceMethod(ClassReflection $classReflection, string $methodName): bool
    {
        foreach ($classReflection->getInterfaces() as $interface) {
            if ($interface->hasMethod($methodName)) {
                return true;
            }
        }

        return false;
    }
}
