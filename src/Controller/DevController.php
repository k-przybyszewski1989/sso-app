<?php

declare(strict_types=1);

namespace App\Controller;

use App\Request\ParamConverter\RequestTransform;
use App\Request\TestRequest;
use App\Response\TestResponse;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DevController extends AbstractController
{
    #[Route('/dev', name: 'dev_index', methods: ['GET'])]
    public function index(): Response
    {
        return new JsonResponse([
            'environment' => $this->getParameter('kernel.environment'),
            'debug' => $this->getParameter('kernel.debug'),
            'message' => 'Development controller - available only in dev environment',
        ]);
    }

    #[Route('/dev/pc', name: 'app_dev_paramconv', methods: ['POST'])]
    public function paramConv(
        #[RequestTransform]
        TestRequest $request,
    ): Response {
        $d = new TestResponse('1', new DateTimeImmutable());

        return new JsonResponse($d);
    }

    #[Route('/dev/info', name: 'dev_info', methods: ['GET'])]
    public function info(): Response
    {
        return new JsonResponse([
            'php_version' => PHP_VERSION,
            'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'database_url' => $this->getParameter('env(DATABASE_URL)') ? 'configured' : 'not configured',
            'timezone' => date_default_timezone_get(),
        ]);
    }
}
