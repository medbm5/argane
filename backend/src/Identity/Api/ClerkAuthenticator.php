<?php

declare(strict_types=1);

namespace App\Identity\Api;

use App\Identity\Application\InvalidSessionToken;
use App\Identity\Application\SessionTokenVerifier;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Stateless API authentication: `Authorization: Bearer <Clerk session JWT>`.
 * Requests without a bearer token continue anonymously (public endpoints).
 */
final class ClerkAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly SessionTokenVerifier $verifier,
        private readonly UserRepository $users,
    ) {
    }

    public function supports(Request $request): bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = trim(substr((string) $request->headers->get('Authorization'), 7));
        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Jeton de session manquant.');
        }

        try {
            $session = $this->verifier->verify($token);
        } catch (InvalidSessionToken $e) {
            throw new CustomUserMessageAuthenticationException('Session invalide ou expirée.', [], 0, $e);
        }

        return new SelfValidatingPassport(new UserBadge(
            $session->clerkUserId,
            fn (string $clerkUserId): User => $this->users->findByClerkId($clerkUserId)
                ?? throw new UserNotFoundException(),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Let ProblemJsonListener render the 401 as problem+json.
        throw new UnauthorizedHttpException('Bearer', $exception->getMessageKey(), $exception);
    }
}
