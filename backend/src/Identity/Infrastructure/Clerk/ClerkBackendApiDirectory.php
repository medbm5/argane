<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Clerk;

use App\Identity\Application\ClerkDirectory;
use App\Identity\Application\Dto\ClerkProfile;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * GET https://api.clerk.com/v1/users/{id} with the instance secret key.
 */
final readonly class ClerkBackendApiDirectory implements ClerkDirectory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(CLERK_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(default:clerk_default_api_url:CLERK_API_URL)%')]
        private string $apiUrl,
    ) {
    }

    public function fetchProfile(string $clerkUserId): ClerkProfile
    {
        if ('' === $this->secretKey) {
            throw new RuntimeException('CLERK_SECRET_KEY is not configured.');
        }

        $data = $this->httpClient->request('GET', rtrim($this->apiUrl, '/').'/users/'.rawurlencode($clerkUserId), [
            'auth_bearer' => $this->secretKey,
            'timeout' => 5,
        ])->toArray();

        return self::mapUser($clerkUserId, $data);
    }

    /**
     * @param array<mixed> $data Clerk User object
     */
    public static function mapUser(string $clerkUserId, array $data): ClerkProfile
    {
        $primaryId = $data['primary_email_address_id'] ?? null;
        $email = null;
        foreach (\is_array($data['email_addresses'] ?? null) ? $data['email_addresses'] : [] as $address) {
            if (!\is_array($address) || !\is_string($address['email_address'] ?? null)) {
                continue;
            }
            if (null === $email || ($address['id'] ?? null) === $primaryId) {
                $email = $address['email_address'];
            }
        }
        if (null === $email) {
            throw new RuntimeException(sprintf('Clerk user %s has no email address.', $clerkUserId));
        }

        $name = trim(implode(' ', array_filter([
            \is_string($data['first_name'] ?? null) ? $data['first_name'] : null,
            \is_string($data['last_name'] ?? null) ? $data['last_name'] : null,
        ])));
        if ('' === $name) {
            $name = \is_string($data['username'] ?? null) && '' !== $data['username']
                ? $data['username']
                : strstr($email, '@', true);
        }

        return new ClerkProfile($clerkUserId, $email, mb_substr((string) $name, 0, 120));
    }
}
