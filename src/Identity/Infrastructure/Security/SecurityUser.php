<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Ordering\Domain\ValueObject\CustomerId;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Přihlášený zákazník. Aplikace z něj potřebuje jen identitu zákazníka.
 */
final readonly class SecurityUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @param non-empty-string $email
     * @param list<string>     $roles
     */
    public function __construct(
        private string $email,
        private CustomerId $customerId,
        private string $passwordHash,
        private array $roles = ['ROLE_CUSTOMER'],
    ) {}

    public function customerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return [...$this->roles, 'ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->passwordHash;
    }
}
