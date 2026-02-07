<?php

namespace App\Security;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(private EntityManagerInterface $em) {}

    public function supports(Request $request): ?bool {
        return $request->headers->has('Authorization') &&
            str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport {
        $apiToken = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        if (null === $apiToken) { throw new AuthenticationException('No API token provided'); }

        // Раскодируем email из нашего фейкового токена 'orion_token_base64email'
        $encodedEmail = str_replace('orion_token_', '', $apiToken);
        $email = base64_decode($encodedEmail);

        return new SelfValidatingPassport(new UserBadge($email));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response {
        return null; // Разрешаем запрос идти дальше к контроллеру
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response {
        return new JsonResponse(['message' => 'Invalid Token'], Response::HTTP_UNAUTHORIZED);
    }
}
