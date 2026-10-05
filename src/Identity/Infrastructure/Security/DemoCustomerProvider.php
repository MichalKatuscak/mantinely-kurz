<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Ordering\Domain\ValueObject\CustomerId;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Pevný seznam ukázkových uživatelů. Heslo všech je „heslo“.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final class DemoCustomerProvider implements UserProviderInterface
{
    public const string ALICE = '0192f0a0-1c3e-7a11-8c00-000000000001';
    public const string BOB = '0192f0a0-1c3e-7b0b-8c00-000000000002';

    private const string PASSWORD_HASH = '$2y$10$lm4J4l9k2013EDlBE6cXYuONKYnlFjJqw.jkm283JR46ztPmKLyWW';

    public const string STAFF = '0192f0a0-1c3e-7c00-8c00-000000000003';

    /** @var array<non-empty-string, array{string, list<string>}> */
    private const array USERS = [
        'alice@example.com' => [self::ALICE, ['ROLE_CUSTOMER']],
        'bob@example.com' => [self::BOB, ['ROLE_CUSTOMER']],
        'sprava@example.com' => [self::STAFF, ['ROLE_STAFF']],
    ];

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        foreach (self::USERS as $email => [$customerId, $roles]) {
            if ($email === $identifier) {
                return new SecurityUser($email, CustomerId::fromString($customerId), self::PASSWORD_HASH, $roles);
            }
        }

        $exception = new UserNotFoundException();
        $exception->setUserIdentifier($identifier);

        throw $exception;
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === SecurityUser::class;
    }
}
