<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MessageController extends AbstractController
{
    #[Route('/api/message', name: 'app_api_message')]
    public function index(): Response
    {
        return $this->render('api/message/index.html.twig', [
            'controller_name' => 'Api/MessageController',
        ]);
    }
}
