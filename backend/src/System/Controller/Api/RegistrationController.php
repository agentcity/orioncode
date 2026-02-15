<?php

namespace App\System\Controller\Api;

use App\System\Service\RegisterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{JsonResponse, Request};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    // 🚀 ВНЕДРЯЕМ ЧЕРЕЗ КОНСТРУКТОР
    public function __construct(
        private RegisterService $registerService,
        private RateLimiterFactoryInterface $registrationLoginLimiter,
        private ValidatorInterface $validator
    ) {}

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(
        Request $request,
    ): JsonResponse
    {
        // Создаем ключ на основе IP пользователя RateLimiter
        $limiter = $this->registrationLoginLimiter->create($request->getClientIp());

        // ПРОВЕРКА: Если лимит исчерпан — выкидываем ошибку
        if (false === $limiter->consume(1)->isAccepted()) {
            return new JsonResponse(['message' => 'TOO_MANY_REQUESTS'], 429);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['message' => 'Email и пароль обязательны'], 400);
        }

        $email = $data['email'] ?? '';

        $emailConstraint = new Assert\Email(
            message: 'INVALID_EMAIL_FORMAT'
        );

        $errors = $this->validator->validate($email, [
            new Assert\NotBlank(message: 'EMAIL_REQUIRED'),
            $emailConstraint]);

        if (count($errors) > 0 || empty($email)) {
            return new JsonResponse(['message' => 'INVALID_EMAIL'], 400);
        }

        // 3. Проверка пароля (минимум 6 символов для безопасности Конного Двора)
        if (empty($data['password']) || strlen($data['password']) < 6) {
            return new JsonResponse(['message' => 'WEAK_PASSWORD'], 400);
        }


        try {
            $this->registerService->register(
                $data['email'],
                $data['password'],
                $data['firstName'] ?? 'User'
            );

            return new JsonResponse(['message' => 'Аккаунт успешно создан. Добро пожаловать!'], 201);
        } catch (\Exception $e) {
            // Если email уже занят, Doctrine выбросит исключение
            $message = str_contains($e->getMessage(), 'Duplicate entry')
                ? 'Этот Email уже зарегистрирован'
                : $e->getMessage();

            return new JsonResponse(['message' => $message], 400);
        }
    }
}
