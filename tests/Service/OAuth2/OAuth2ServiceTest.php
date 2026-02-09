<?php

declare(strict_types=1);

namespace App\Tests\Service\OAuth2;

use App\Enum\GrantType;
use App\Exception\OAuth2\UnsupportedGrantTypeException;
use App\Request\OAuth2\TokenRequest;
use App\Response\OAuth2\TokenResponse;
use App\Service\OAuth2\Grant\GrantHandlerInterface;
use App\Service\OAuth2\OAuth2Service;
use ArrayIterator;
use PHPUnit\Framework\TestCase;

final class OAuth2ServiceTest extends TestCase
{
    public function testIssueTokenDelegatesToCorrectHandler(): void
    {
        $request = new TokenRequest(grantType: GrantType::CLIENT_CREDENTIALS);

        $tokenResponse = new TokenResponse(
            accessToken: 'test_token',
            tokenType: 'Bearer',
            expiresIn: 3600
        );

        $handler1 = $this->createMock(GrantHandlerInterface::class);
        $handler1->expects($this->once())
            ->method('supports')
            ->with('client_credentials')
            ->willReturn(false);
        $handler1->expects($this->never())
            ->method('handle');

        $handler2 = $this->createMock(GrantHandlerInterface::class);
        $handler2->expects($this->once())
            ->method('supports')
            ->with('client_credentials')
            ->willReturn(true);
        $handler2->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($tokenResponse);

        $handler3 = $this->createMock(GrantHandlerInterface::class);
        $handler3->expects($this->never())
            ->method('supports');
        $handler3->expects($this->never())
            ->method('handle');

        $service = new OAuth2Service([$handler1, $handler2, $handler3]);
        $result = $service->issueToken($request);

        $this->assertSame($tokenResponse, $result);
    }

    public function testIssueTokenThrowsExceptionWhenNoHandlerSupportsGrantType(): void
    {
        $request = new TokenRequest(grantType: GrantType::CLIENT_CREDENTIALS);

        $handler1 = $this->createMock(GrantHandlerInterface::class);
        $handler1->expects($this->once())
            ->method('supports')
            ->with('client_credentials')
            ->willReturn(false);

        $handler2 = $this->createMock(GrantHandlerInterface::class);
        $handler2->expects($this->once())
            ->method('supports')
            ->with('client_credentials')
            ->willReturn(false);

        $service = new OAuth2Service([$handler1, $handler2]);

        $this->expectException(UnsupportedGrantTypeException::class);
        $this->expectExceptionMessage('Grant type "client_credentials" is not supported');

        $service->issueToken($request);
    }

    public function testIssueTokenWorksWithIterableHandlers(): void
    {
        $request = new TokenRequest(grantType: GrantType::AUTHORIZATION_CODE);

        $tokenResponse = new TokenResponse(
            accessToken: 'test_token',
            tokenType: 'Bearer',
            expiresIn: 3600
        );

        $handler = $this->createMock(GrantHandlerInterface::class);
        $handler->expects($this->once())
            ->method('supports')
            ->with('authorization_code')
            ->willReturn(true);
        $handler->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($tokenResponse);

        $iterator = new ArrayIterator([$handler]);

        $service = new OAuth2Service($iterator);
        $result = $service->issueToken($request);

        $this->assertSame($tokenResponse, $result);
    }

    public function testIssueTokenStopsAtFirstMatchingHandler(): void
    {
        $request = new TokenRequest(grantType: GrantType::REFRESH_TOKEN);

        $tokenResponse = new TokenResponse(
            accessToken: 'test_token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            refreshToken: 'new_refresh_token'
        );

        $handler1 = $this->createMock(GrantHandlerInterface::class);
        $handler1->expects($this->once())
            ->method('supports')
            ->with('refresh_token')
            ->willReturn(true);
        $handler1->expects($this->once())
            ->method('handle')
            ->with($request)
            ->willReturn($tokenResponse);

        $handler2 = $this->createMock(GrantHandlerInterface::class);
        $handler2->expects($this->never())
            ->method('supports');
        $handler2->expects($this->never())
            ->method('handle');

        $service = new OAuth2Service([$handler1, $handler2]);
        $result = $service->issueToken($request);

        $this->assertSame($tokenResponse, $result);
    }
}
