<?php

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;
use App\Entity\Account;
use App\Repository\AccountRepository;

class AccountController extends AbstractController
{
    #[Route('/api/accounts', name: 'api_accounts_index', methods: ['GET'])]
    public function index(AccountRepository $accountRepository, SerializerInterface $serializer): JsonResponse
    {
        // Получаем только аккаунты текущего пользователя
        $accounts = $accountRepository->findBy(['user' => $this->getUser()]);

        // Сериализуем в JSON с определенными группами
        $json = $serializer->serialize($accounts, 'json', ['groups' => ['account:read']]);

        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/api/accounts', name: 'api_accounts_create', methods: ['POST'])]
    public function create(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager): JsonResponse
    {
        $account = $serializer->deserialize($request->getContent(), Account::class, 'json', ['groups' => ['account:write']]);
        $account->setUser($this->getUser()); // Присваиваем текущего пользователя
        // ... дополнительная валидация и обработка credentials (шифрование!)

        $entityManager->persist($account);
        $entityManager->flush();

        $json = $serializer->serialize($account, 'json', ['groups' => ['account:read']]);
        return new JsonResponse($json, 201, [], true);
    }
    // ...
}
