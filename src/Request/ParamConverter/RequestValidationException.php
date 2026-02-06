<?php

declare(strict_types=1);

namespace App\Request\ParamConverter;

final class RequestValidationException extends \InvalidArgumentException
{
	/** @param array<int, array{path: string, message: string}> $violations */
	public function __construct(private array $violations)
	{
		parent::__construct();
	}

	/** @return array<int, array{path: string, message: string}> */
	public function getViolations(): array
	{
		return $this->violations;
	}
}
