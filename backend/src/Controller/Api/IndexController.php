<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

// Роут теперь задан в yaml, здесь можно оставить для наглядности
class IndexController extends AbstractController
{
    public function index(): Response
    {
        $path = $this->getParameter('kernel.project_dir') . '/public/api_welcome.html';
        return new Response(file_get_contents($path));
    }
}

