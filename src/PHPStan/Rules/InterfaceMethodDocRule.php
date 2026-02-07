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
        $interfaceMethod = $this->getInterfaceMethod($classReflection, $methodReflection->getName(), $scope);
        if (null === $interfaceMethod) {
            return [];
        }

        // Check if interface method has a docblock
        $interfaceDocComment = $interfaceMethod['reflection']->getDocComment();
        $interfaceHasDocblock = null !== $interfaceDocComment && '' !== trim($interfaceDocComment);

        if (!$interfaceHasDocblock) {
            // Interface method is missing a docblock - check if implementation has redundant {@inheritDoc}
            $originalNode = $node->getOriginalNode();
            if (!$originalNode instanceof ClassMethod) {
                return [];
            }

            $docComment = $originalNode->getDocComment();
            if (null !== $docComment) {
                $docText = $docComment->getText();
                if (str_contains($docText, '@inheritDoc') || str_contains($docText, '{@inheritDoc}')) {
                    return [
                        RuleErrorBuilder::message(sprintf(
                            'Method %s::%s() has redundant {@inheritDoc} annotation as interface method %s::%s() has no documentation.',
                            $classReflection->getName(),
                            $methodReflection->getName(),
                            $interfaceMethod['interface']->getName(),
                            $methodReflection->getName()
                        ))
                        ->identifier('redundantInheritDoc')
                        ->build(),
                    ];
                }
            }

            // Report interface missing docblock
            return [
                RuleErrorBuilder::message(sprintf(
                    'Interface method %s::%s() is missing a docblock.',
                    $interfaceMethod['interface']->getName(),
                    $methodReflection->getName()
                ))
                ->identifier('missingInterfaceDocblock')
                ->build(),
            ];
        }

        // Interface has docblock, check if implementation has {@inheritDoc}
        $originalNode = $node->getOriginalNode();
        if (!$originalNode instanceof ClassMethod) {
            return [];
        }

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

    /**
     * @return array{interface: ClassReflection, reflection: \PHPStan\Reflection\MethodReflection}|null
     */
    private function getInterfaceMethod(ClassReflection $classReflection, string $methodName, Scope $scope): ?array
    {
        foreach ($classReflection->getInterfaces() as $interface) {
            if ($interface->hasMethod($methodName)) {
                return [
                    'interface' => $interface,
                    'reflection' => $interface->getMethod($methodName, $scope),
                ];
            }
        }

        return null;
    }
}
