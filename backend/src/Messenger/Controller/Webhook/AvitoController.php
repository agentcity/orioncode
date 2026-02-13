<?php

namespace App\Messenger\Controller\Webhook;

use App\Entity\{Account, Contact, Conversation, Message};
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Routing\Attribute\Route;

class AvitoController extends AbstractController
{
    #[Route('/api/webhooks/avito/{accountId}', methods: ['POST'])]
    public function handle(
        string $accountId,
        Request $request,
        EntityManagerInterface $em,
        ChatService $chatService
    ): Response {
        $account = $em->getRepository(Account::class)->find($accountId);
        if (!$account) return new JsonResponse(['error' => 'Account not found'], 404);

        $data = json_decode($request->getContent(), true);

        // Авито шлет тип 'message' в поле 'type'
        if (isset($data['payload']['value']['text'])) {
            $payload = $data['payload']['value'];
            $chatId = $payload['chat_id'];
            $userId = (string)$payload['user_id'];
            $text = $payload['text'];
            $authorId = (string)$payload['author_id'];

            // Игнорируем собственные сообщения (от самого Авито-аккаунта)
            // Обычно в Авито API это проверяется сравнением authorId и clientId

            // 1. Находим/Создаем контакт
            $contact = $em->getRepository(Contact::class)->findOneBy([
                'externalId' => $userId,
                'account' => $account
            ]);

            if (!$contact) {
                $contact = (new Contact())
                    ->setExternalId($userId)
                    ->setSource('avito')
                    ->setMainName("Клиент Авито " . substr($userId, -4))
                    ->setAccount($account);
                $em->persist($contact);
            }

            // 2. Находим/Создаем беседу
            $conv = $em->getRepository(Conversation::class)->findOneBy([
                'contact' => $contact,
                'account' => $account
            ]);

            if (!$conv) {
                $conv = (new Conversation())
                    ->setContact($contact)
                    ->setAccount($account)
                    ->setType('avito')
                    ->setStatus('active')
                    ->setAssignedTo($account->getOwner());
                $em->persist($conv);
            }

            // 3. Сохраняем сообщение
            $msg = (new Message())
                ->setConversation($conv)
                ->setText($text)
                ->setDirection('inbound')
                ->setSenderType('contact')
                ->setContact($contact)
                ->setSentAt(new \DateTimeImmutable());

            $em->persist($msg);
            $em->flush();

            // 4. Отправляем в сокеты для Android
            // Здесь ChatService пробросит инфу в Redis
        }

        return new Response('ok', 200);
    }
}
