<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConversationController extends AbstractController
{
    #[Route('/api/conversation', name: 'app_api_conversation')]
    public function index(): Response
    {
        return $this->render('api/conversation/index.html.twig', [
            'controller_name' => 'Api/ConversationController',
        ]);
    }
}
