<?php

declare(strict_types=1);

namespace App\Tests\Legacy;

use App\Legacy\lib\AccessDenied;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * auth_require() a csrf_*() ze staré administrace bez jádra Symfony.
 * Přihlášeného uživatele a token jinak nastavuje LegacyFrontController.
 */
final class SecurityFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__.'/../../src/Legacy/bootstrap.php';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['LEGACY_USER'], $GLOBALS['LEGACY_CSRF_TOKEN'], $_POST['_csrf']);
    }

    #[Test]
    public function withoutUserNothingIsAllowed(): void
    {
        $this->expectException(AccessDenied::class);

        \auth_require();
    }

    #[Test]
    public function userWithoutRoleIsDeniedRoleAction(): void
    {
        $this->logIn('');

        $this->expectException(AccessDenied::class);

        \auth_require('obchod');
    }

    #[Test]
    public function userWithoutRoleMayOpenPageWithoutRole(): void
    {
        $this->logIn('');

        self::assertTrue(\auth_require());
    }

    #[Test]
    public function userWithOtherRoleIsDenied(): void
    {
        $this->logIn('sklad');

        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessage('Nemáte oprávnění');

        \auth_require('obchod');
    }

    #[Test]
    public function userWithRoleIsAllowed(): void
    {
        $this->logIn('obchod');

        self::assertTrue(\auth_require('obchod'));
    }

    #[Test]
    public function adminIsAllowedEverything(): void
    {
        $this->logIn('admin');

        self::assertTrue(\auth_require('obchod'));
        self::assertTrue(\auth_require('sklad'));
        self::assertTrue(\auth_require('ucetni'));
    }

    #[Test]
    public function tokenIsGeneratedOnceAndKept(): void
    {
        $token = \csrf_token();

        self::assertIsString($token);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertSame($token, \csrf_token());
    }

    #[Test]
    public function tokenFromFrontControllerIsUsed(): void
    {
        $GLOBALS['LEGACY_CSRF_TOKEN'] = 'token-ze-session';

        self::assertSame('token-ze-session', \csrf_token());
    }

    #[Test]
    public function fieldCarriesEscapedToken(): void
    {
        $GLOBALS['LEGACY_CSRF_TOKEN'] = 'a"b';

        self::assertSame('<input type="hidden" name="_csrf" value="a&quot;b">', \csrf_field());
    }

    #[Test]
    public function checkPassesWithTokenFromField(): void
    {
        $_POST['_csrf'] = \csrf_token();

        self::assertTrue(\csrf_check());
    }

    #[Test]
    public function checkFailsWithoutToken(): void
    {
        \csrf_token();

        $this->expectException(AccessDenied::class);
        $this->expectExceptionMessage('Neplatný bezpečnostní token formuláře');

        \csrf_check();
    }

    #[Test]
    public function checkFailsWithWrongToken(): void
    {
        \csrf_token();
        $_POST['_csrf'] = str_repeat('0', 64);

        $this->expectException(AccessDenied::class);

        \csrf_check();
    }

    #[Test]
    public function checkFailsWithTokenAsArray(): void
    {
        $_POST['_csrf'] = [\csrf_token()];

        $this->expectException(AccessDenied::class);

        \csrf_check();
    }

    private function logIn(string $role): void
    {
        $GLOBALS['LEGACY_USER'] = ['id' => null, 'login' => 'test@example.com', 'role' => $role];
    }
}
