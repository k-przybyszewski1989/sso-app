<?php

declare(strict_types=1);

namespace App\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class JsonFunctionsMustThrowRule implements Rule
{
    /**
     * {@inheritDoc}
     */
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * {@inheritDoc}
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = $node->name->toLowerString();

        if (!in_array($functionName, ['json_encode', 'json_decode'], true)) {
            return [];
        }

        $hasThrowFlag = false;
        $flagPosition = 'json_encode' === $functionName ? 1 : 3;

        if (isset($node->args[$flagPosition])) {
            $arg = $node->args[$flagPosition];

            if (!$arg instanceof Arg) {
                return [];
            }

            $flagValue = $arg->value;

            if ($flagValue instanceof Node\Expr\ConstFetch
                && 'JSON_THROW_ON_ERROR' === $flagValue->name->toString()) {
                $hasThrowFlag = true;
            }

            if ($flagValue instanceof Node\Expr\BinaryOp\BitwiseOr) {
                $hasThrowFlag = $this->checkBitwiseOrForFlag($flagValue);
            }
        }

        if (!$hasThrowFlag) {
            return [
                RuleErrorBuilder::message(
                    sprintf('%s() must use JSON_THROW_ON_ERROR flag', $functionName)
                )->identifier('json.throwOnError')
                ->build(),
            ];
        }

        return [];
    }

    private function checkBitwiseOrForFlag(Node\Expr\BinaryOp\BitwiseOr $node): bool
    {
        if ($node->left instanceof Node\Expr\ConstFetch
            && 'JSON_THROW_ON_ERROR' === $node->left->name->toString()) {
            return true;
        }

        if ($node->right instanceof Node\Expr\ConstFetch
            && 'JSON_THROW_ON_ERROR' === $node->right->name->toString()) {
            return true;
        }

        if ($node->left instanceof Node\Expr\BinaryOp\BitwiseOr) {
            return $this->checkBitwiseOrForFlag($node->left);
        }

        if ($node->right instanceof Node\Expr\BinaryOp\BitwiseOr) {
            return $this->checkBitwiseOrForFlag($node->right);
        }

        return false;
    }
}
