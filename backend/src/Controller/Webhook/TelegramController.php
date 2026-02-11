<?php
namespace App\Controller\Webhook;

use App\Entity\{Account, Contact, Conversation, Message};
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class TelegramController extends AbstractController {
    #[Route('/api/webhooks/telegram/{accountId}', methods: ['POST'])]
    public function handle(string $accountId, Request $request, EntityManagerInterface $em, ChatService $chatService): JsonResponse {
        $account = $em->getRepository(Account::class)->find($accountId);
        if (!$account) return $this->json(['error' => 'Account not found'], 404);

        $data = json_decode($request->getContent(), true);
        if (!isset($data['message']['text'])) return $this->json(['ok' => true]);

        $tgId = (string)$data['message']['from']['id'];
        $text = $data['message']['text'];
        $name = ($data['message']['from']['first_name'] ?? 'User') . ' ' . ($data['message']['from']['last_name'] ?? '');

        // Ищем/Создаем контакт и беседу ВНУТРИ аккаунта
        $contact = $em->getRepository(Contact::class)->findOneBy(['externalId' => $tgId, 'account' => $account])
            ?? (new Contact())->setExternalId($tgId)->setSource('telegram')->setMainName($name)->setAccount($account);

        $conv = $em->getRepository(Conversation::class)->findOneBy(['contact' => $contact, 'account' => $account])
            ?? (new Conversation())->setContact($contact)->setAccount($account)->setType('telegram')->setStatus('active');

        if (!$contact->getId()) $em->persist($contact);
        if (!$conv->getId()) $em->persist($conv);

        $msg = (new Message())->setConversation($conv)->setText($text)->setDirection('inbound')->setSentAt(new \DateTimeImmutable());
        $em->persist($msg);
        $em->flush();

        // Redis Push для фронтенда
        $this->broadcast($conv->getId()->toString(), $msg);

        // Авто-ответ ИИ (он сам ответит в TG через ChatService)
        $chatService->generateAiReply($conv, $text);

        return $this->json(['ok' => true]);
    }

    private function broadcast($convId, $msg) {
        $redis = RedisAdapter::createConnection($_ENV['REDIS_URL'] ?? 'redis://orion_redis:6379');
        $redis->publish('chat_messages', json_encode([
            'conversationId' => $convId,
            'payload' => ['id' => $msg->getId()->toString(), 'text' => $msg->getText(), 'direction' => 'inbound']
        ]));
    }
}
