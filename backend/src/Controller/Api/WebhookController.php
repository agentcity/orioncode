<?php

namespace App\Controller\Api;

use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Predis\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    #[Route('/api/webhooks/status', methods: ['POST'])]
    public function updateStatus(Request $request, MessageRepository $repository, EntityManagerInterface $em, ClientInterface $redis): Response
    {
        $data = json_decode($request->getContent(), true);

        // Допустим, провайдер прислал external_id и новый статус
        $message = $repository->findOneBy(['externalId' => $data['external_id']]);

        if ($message) {
            $message->setStatus($data['status']); // delivered, read
            $em->flush();

            // Оповещаем фронтенд через WebSocket
            $redis->publish('new_message_channel', json_encode([
                'id' => $message->getId()->toString(),
                'conversationId' => $message->getConversation()->getId()->toString(),
                'status' => $message->getStatus(),
                'event' => 'statusUpdate'
            ]));
        }

        return new Response('OK');
    }
}
