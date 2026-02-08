<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\OAuth2\CreateClientRequest;
use App\Request\ParamConverter\RequestTransform;
use App\Service\OAuth2\ClientManagementServiceInterface;
use DateTimeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients')]
#[IsGranted('ROLE_ADMIN')]
final class ClientController extends AbstractController
{
    public function __construct(
        private readonly ClientManagementServiceInterface $clientManagementService,
    ) {
    }

    #[Route('', name: 'client_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $clients = $this->clientManagementService->listClients();

        $data = array_map(
            static fn ($client) => [
                'client_id' => $client->getClientId(),
                'name' => $client->getName(),
                'description' => $client->getDescription(),
                'redirect_uris' => $client->getRedirectUris(),
                'grant_types' => $client->getGrantTypes(),
                'allowed_scopes' => $client->getAllowedScopes(),
                'confidential' => $client->isConfidential(),
                'active' => $client->isActive(),
                'created_at' => $client->getCreatedAt()->format(DateTimeInterface::ATOM),
                'updated_at' => $client->getUpdatedAt()->format(DateTimeInterface::ATOM),
            ],
            $clients,
        );

        return new JsonResponse($data);
    }

    #[Route('', name: 'client_create', methods: ['POST'])]
    public function create(
        #[RequestTransform(validate: true)]
        CreateClientRequest $request,
    ): JsonResponse {
        $clientData = $this->clientManagementService->createClient(
            $request->name,
            $request->redirectUris,
            $request->grantTypes,
            $request->confidential,
        );

        return new JsonResponse($clientData, Response::HTTP_CREATED);
    }

    #[Route('/{clientId}', name: 'client_delete', methods: ['DELETE'])]
    public function delete(string $clientId): JsonResponse
    {
        $this->clientManagementService->deleteClient($clientId);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
