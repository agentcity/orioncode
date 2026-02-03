<?php

// src/Controller/Webhook/TelegramWebhookController.php
namespace App\Controller\Webhook;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\IncomingTelegramMessage;

class TelegramWebhookController extends AbstractController
{
    #[Route('/webhook/telegram/{token}', name: 'webhook_telegram', methods: ['POST'])]
    public function handle(string $token, Request $request, MessageBusInterface $messageBus): Response
    {
        // TODO: Валидация токена (например, сравнить с токенами из БД, чтобы убедиться, что это наш аккаунт)
        // Для начала можно просто найти Account по токену

        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new Response('Invalid JSON', Response::HTTP_BAD_REQUEST);
        }

        // Отправляем сырой payload в очередь для асинхронной обработки
        $messageBus->dispatch(new IncomingTelegramMessage($token, $payload));

        return new Response('OK', Response::HTTP_OK);
    }
}
