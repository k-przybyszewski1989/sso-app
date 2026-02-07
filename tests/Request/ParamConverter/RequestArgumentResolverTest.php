<?php

declare(strict_types=1);

namespace App\Tests\Request\ParamConverter;

use App\Request\ParamConverter\RequestArgumentResolver;
use App\Request\ParamConverter\RequestTransform;
use App\Request\ParamConverter\RequestValidationException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestArgumentResolverTest extends TestCase
{
    private function createResolver(
        ?SerializerInterface $serializer = null,
        ?ValidatorInterface $validator = null,
        ?LoggerInterface $logger = null,
    ): RequestArgumentResolver {
        return new RequestArgumentResolver(
            $serializer ?? $this->createStub(SerializerInterface::class),
            $validator ?? $this->createStub(ValidatorInterface::class),
            $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    public function testResolveReturnsEmptyArrayWhenTypeIsNotString(): void
    {
        $resolver = $this->createResolver();
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(null);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertEmpty($result);
    }

    public function testResolveReturnsEmptyArrayWhenClassDoesNotExist(): void
    {
        $resolver = $this->createResolver();
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn('NonExistentClass');

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertEmpty($result);
    }

    public function testResolveReturnsEmptyArrayWhenNoRequestTransformAttributes(): void
    {
        $resolver = $this->createResolver();
        $request = new Request();
        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertEmpty($result);
    }

    public function testResolveDeserializesJsonContentSuccessfully(): void
    {
        $jsonContent = '{"key":"value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $deserializedObject = new \stdClass();
        $deserializedObject->key = 'value';

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->with($jsonContent, \stdClass::class, 'json')
            ->willReturn($deserializedObject);

        $resolver = $this->createResolver(serializer: $serializer);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: false)]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $result);
        $this->assertSame($deserializedObject, $result[0]);
    }

    public function testResolveDeserializesFormDataSuccessfully(): void
    {
        $formData = ['key' => 'value'];
        $request = new Request([], $formData);
        $deserializedObject = new \stdClass();
        $deserializedObject->key = 'value';

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->with(json_encode($formData), \stdClass::class, 'json')
            ->willReturn($deserializedObject);

        $resolver = $this->createResolver(serializer: $serializer);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: false)]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $result);
        $this->assertSame($deserializedObject, $result[0]);
    }

    public function testResolveHandlesDeserializationExceptionAndCreatesEmptyObject(): void
    {
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], 'invalid json');
        $exception = new \Exception('Deserialization failed');

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willThrowException($exception);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Request input deserialization exception', [
                'exception' => $exception,
            ]);

        $resolver = $this->createResolver(serializer: $serializer, logger: $logger);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: false)]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $result);
        $this->assertInstanceOf(\stdClass::class, $result[0]);
    }

    public function testResolveValidatesObjectWhenValidationEnabled(): void
    {
        $jsonContent = '{"key":"value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $deserializedObject = new \stdClass();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($deserializedObject);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($deserializedObject)
            ->willReturn(new ConstraintViolationList());

        $resolver = $this->createResolver(serializer: $serializer, validator: $validator);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: true)]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $result);
        $this->assertSame($deserializedObject, $result[0]);
    }

    public function testResolveThrowsExceptionWhenValidationFails(): void
    {
        $jsonContent = '{"key":"value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $deserializedObject = new \stdClass();

        $violation = new ConstraintViolation(
            'Invalid value',
            'Invalid value',
            [],
            $deserializedObject,
            'key',
            'value'
        );
        $violationList = new ConstraintViolationList([$violation]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($deserializedObject);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($deserializedObject)
            ->willReturn($violationList);

        $resolver = $this->createResolver(serializer: $serializer, validator: $validator);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: true)]);

        $this->expectException(RequestValidationException::class);

        iterator_to_array($resolver->resolve($request, $argument));
    }

    public function testResolveSkipsValidationWhenValidationDisabled(): void
    {
        $jsonContent = '{"key":"value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $deserializedObject = new \stdClass();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($deserializedObject);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())
            ->method('validate');

        $resolver = $this->createResolver(serializer: $serializer, validator: $validator);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: false)]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $result);
        $this->assertSame($deserializedObject, $result[0]);
    }

    public function testResolveHandlesMultipleRequestTransformAttributes(): void
    {
        $jsonContent = '{"key":"value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $deserializedObject1 = new \stdClass();
        $deserializedObject2 = new \stdClass();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->exactly(2))
            ->method('deserialize')
            ->willReturnOnConsecutiveCalls($deserializedObject1, $deserializedObject2);

        $resolver = $this->createResolver(serializer: $serializer);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([
                new RequestTransform(validate: false),
                new RequestTransform(validate: false),
            ]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(2, $result);
        $this->assertSame($deserializedObject1, $result[0]);
        $this->assertSame($deserializedObject2, $result[1]);
    }

    public function testResolveValidationExceptionContainsCorrectViolations(): void
    {
        $jsonContent = '{"key":"value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $deserializedObject = new \stdClass();

        $violation1 = new ConstraintViolation(
            'Error 1',
            'Error 1',
            [],
            $deserializedObject,
            'field1',
            'value1'
        );
        $violation2 = new ConstraintViolation(
            'Error 2',
            'Error 2',
            [],
            $deserializedObject,
            'field2',
            'value2'
        );
        $violationList = new ConstraintViolationList([$violation1, $violation2]);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->willReturn($deserializedObject);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($deserializedObject)
            ->willReturn($violationList);

        $resolver = $this->createResolver(serializer: $serializer, validator: $validator);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\stdClass::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: true)]);

        try {
            iterator_to_array($resolver->resolve($request, $argument));
            $this->fail('Expected RequestValidationException was not thrown');
        } catch (RequestValidationException $exception) {
            $violations = $exception->getViolations();
            $this->assertCount(2, $violations);
            $this->assertSame('field1', $violations[0]['path']);
            $this->assertSame('Error 1', $violations[0]['message']);
            $this->assertSame('field2', $violations[1]['path']);
            $this->assertSame('Error 2', $violations[1]['message']);
        }
    }

    public function testResolveWorksWithInterfaceType(): void
    {
        $jsonContent = '{"key":"value"}';
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $jsonContent);
        $deserializedObject = new \stdClass();

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('deserialize')
            ->with($jsonContent, \Iterator::class, 'json')
            ->willReturn($deserializedObject);

        $resolver = $this->createResolver(serializer: $serializer);

        $argument = $this->createMock(ArgumentMetadata::class);
        $argument->expects($this->once())
            ->method('getType')
            ->willReturn(\Iterator::class);
        $argument->expects($this->once())
            ->method('getAttributes')
            ->with(RequestTransform::class)
            ->willReturn([new RequestTransform(validate: false)]);

        $result = iterator_to_array($resolver->resolve($request, $argument));

        $this->assertCount(1, $result);
        $this->assertSame($deserializedObject, $result[0]);
    }
}
