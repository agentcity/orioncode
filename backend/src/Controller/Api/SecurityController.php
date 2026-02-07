<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SecurityController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?User $user): JsonResponse
    {

        if (null === $user) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        $responseData = [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getUserIdentifier(),
            ],
            'token' => 'orion_token_' . base64_encode($user->getUserIdentifier())

        ];

        return $this->json($responseData);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): void
    {
        // Symfony автоматически перехватывает этот роут.
        // Контроллер может быть пустым.
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
