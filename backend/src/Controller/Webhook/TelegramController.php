<?php
namespace App\Controller\Webhook;

use App\Entity\{Account, Contact, Conversation, Message};
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
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


        // 1. Сначала принудительно сохраняем контакт, если он новый
        if ($em->getUnitOfWork()->getEntityState($contact) === \Doctrine\ORM\UnitOfWork::STATE_NEW) {
            $em->persist($contact);
        }

        // 2. Затем принудительно сохраняем беседу, если она новая
        if ($em->getUnitOfWork()->getEntityState($conv) === \Doctrine\ORM\UnitOfWork::STATE_NEW) {
            // Привязываем главного менеджера (владельца аккаунта "Атаманский Двор")
            if ($account->getUser()) {
                $conv->setAssignedTo($account->getUser());
            }

            $em->persist($conv);
        }



        // 3. Теперь создаем и сохраняем сообщение

        $msg = (new Message())
            ->setConversation($conv)
            ->setText($text)
            ->setDirection('inbound')
            ->setSenderType('contact')
            ->setSentAt(new \DateTimeImmutable());

        $em->persist($msg);
        $em->flush();

        // Redis Push для фронтенда
        $this->broadcast($conv->getId()->toString(), $msg);

        // Авто-ответ ИИ (он сам ответит в TG через ChatService)
        //$chatService->generateAiReply($conv, $text);

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
