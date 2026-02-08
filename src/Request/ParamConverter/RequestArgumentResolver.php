<?php

declare(strict_types=1);

namespace App\Request\ParamConverter;

use Psr\Log\LoggerInterface;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

use function class_exists;
use function interface_exists;
use function is_string;

final readonly class RequestArgumentResolver implements ValueResolverInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @return iterable<array-key, object>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if (false === is_string($type) || (false === class_exists($type) && false === interface_exists($type))) {
            return [];
        }

        /** @var array<array-key, RequestTransform> $attributes */
        $attributes = $argument->getAttributes(RequestTransform::class);

        foreach ($attributes as $attribute) {
            yield $this->deserialize($attribute, $request, $type);
        }
    }

    /** @param class-string $class */
    private function deserialize(RequestTransform $attribute, Request $request, string $class): object
    {
        try {
            $data = 'json' === $request->getContentTypeFormat() ?
                json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR) :
                $request->request->all();

            if (!is_array($data)) {
                $data = [];
            }

            // Add Authorization header to data if the class has an authorizationHeader property
            if (property_exists($class, 'authorizationHeader')) {
                $authHeader = $request->headers->get('Authorization');
                if (null !== $authHeader && !isset($data['authorization_header']) && !isset($data['authorizationHeader'])) {
                    // Use snake_case key since the name converter will convert it to camelCase
                    $data['authorization_header'] = $authHeader;
                }
            }

            $encoded = json_encode($data, JSON_THROW_ON_ERROR);
            $deserialized = $this->serializer->deserialize($encoded, $class, 'json');

            if (!is_object($deserialized)) {
                throw new RuntimeException('Deserialization did not return an object');
            }

            $object = $deserialized;
        } catch (Throwable $exception) {
            $this->logger->error('Request input deserialization exception', [
                'exception' => $exception,
            ]);

            $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        }

        if ($attribute->validate) {
            $violations = $this->getViolationListToArray($this->validator->validate($object));

            if (count($violations) > 0) {
                throw new RequestValidationException($violations);
            }
        }

        return $object;
    }

    /**
     * @param ConstraintViolationListInterface<ConstraintViolationInterface> $violationList
     *
     * @return array<int, array{path: string, message: string}>
     */
    private function getViolationListToArray(ConstraintViolationListInterface $violationList): array
    {
        $result = [];

        /** @var ConstraintViolationInterface $violation */
        foreach ($violationList as $violation) {
            $result[] = [
                'path' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $result;
    }
}
