<?php

namespace App\Controller\Webhook;

use App\Entity\{Account, Contact, Conversation, Message};
use App\Service\ChatService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

class VkController extends AbstractController
{
    #[Route('/api/webhooks/vk/{accountId}', methods: ['POST'])]
    public function handle(string $accountId, Request $request, EntityManagerInterface $em, ChatService $chatService, \App\Service\Messenger\VkMessenger $vkMessenger): \Symfony\Component\HttpFoundation\Response
    {
        $account = $em->getRepository(Account::class)->find($accountId);
        if (!$account) return $this->json(['error' => 'Account not found'], 404);

        $data = json_decode($request->getContent(), true);

//        //--- ЛОГЕР ДЛЯ ОТЛАДКИ 2026 ---
//        file_put_contents(
//            $this->getParameter('kernel.logs_dir') . '/vk_webhook.log',
//            "[" . date('Y-m-d H:i:s') . "] Payload: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n",
//            FILE_APPEND
//        );

        // 1. Подтверждение сервера (для настройки в кабинете ВК)
        if ($data['type'] === 'confirmation') {
            $code = $account->getCredential('vk_confirmation_code');
            // Используем обычный Response вместо JsonResponse, чтобы не было кавычек!
            return new \Symfony\Component\HttpFoundation\Response($code, 200, [
                'Content-Type' => 'text/plain'
            ]);
        }

        // 2. Обработка сообщения
        if ($data['type'] === 'message_new') {
            $msgData = $data['object']['message'];
            $vkId = (string)$msgData['from_id'];
            $text = $msgData['text'];

            $contact = $em->getRepository(Contact::class)->findOneBy(['externalId' => $vkId, 'account' => $account]);


            if (!$contact) {

                // Если контакта нет, запрашиваем имя через API ВК
                $vkToken = $account->getCredential('vk_token');
                // Получаем сервис мессенджера (убедись, что добавил его в аргументы handle или через inject)
                $userData = $vkMessenger->getUserData($vkId, $vkToken);

                $firstName = $userData['first_name'] ?? 'Клиент';
                $lastName  = $userData['last_name'] ?? 'ВК';
                $fullName = trim($firstName . ' ' . $lastName);


//            //--- ЛОГЕР ДЛЯ ОТЛАДКИ 2026 ---
//            file_put_contents(
//                $this->getParameter('kernel.logs_dir') . '/vk_webhook.log',
//                "[" . date('Y-m-d H:i:s') . "] Payload: " . json_encode($userData, JSON_UNESCAPED_UNICODE) . "\n",
//                FILE_APPEND
//            );

                $contact = (new Contact())
                    ->setExternalId($vkId)
                    ->setSource('vk')
                    ->setMainName($fullName)
                    ->setAccount($account);

                // Если есть аватарка, сохраним её в payload
                if (!empty($userData['photo_50'])) {
                    $contact->setPayload(['avatar' => $userData['photo_50']]);
                }
            }


            $conv = $em->getRepository(Conversation::class)->findOneBy(['contact' => $contact, 'account' => $account])
                ?? (new Conversation())->setContact($contact)->setAccount($account)->setType('vk')->setStatus('active')->setAssignedTo($account->getUser());

            $uow = $em->getUnitOfWork();
            if ($uow->getEntityState($contact) === \Doctrine\ORM\UnitOfWork::STATE_NEW) $em->persist($contact);
            if ($uow->getEntityState($conv) === \Doctrine\ORM\UnitOfWork::STATE_NEW) $em->persist($conv);

            $msg = (new Message())
                ->setConversation($conv)
                ->setText($text)
                ->setDirection('inbound')
                ->setSenderType('contact')
                ->setContact($contact)
                ->setManager(null)
                ->setSentAt(new \DateTimeImmutable());
            $em->persist($msg);
            $em->flush();

            // Ответ ИИ и пуш в сокеты через ChatService
            //$chatService->generateAiReply($conv, $text);

            return new \Symfony\Component\HttpFoundation\Response('ok', 200, [
                'Content-Type' => 'text/plain'
            ]);

        }
        return new \Symfony\Component\HttpFoundation\Response('ok', 200, [
            'Content-Type' => 'text/plain'
        ]);
    }
}
