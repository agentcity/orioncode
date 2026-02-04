<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SecurityController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST', 'OPTIONS'])]
    public function login(): Response
    {
        // Symfony перехватит запрос до выполнения этого кода
        return $this->json(['message' => 'This should not be reached!']);
    }

    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(): Response
    {
        // Этот код никогда не выполнится, Symfony перехватит запрос раньше
        return $this->json(['message' => 'This should not be reached!']);
    }
}
