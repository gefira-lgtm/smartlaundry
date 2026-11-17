<?php
/**
 * Test Authentication, RBAC, and Model Logic
 */

require_once dirname(__DIR__) . '/config/env.php';
load_env(dirname(__DIR__) . '/.env');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/app/Core/Database.php';
require_once dirname(__DIR__) . '/app/Core/Model.php';
require_once dirname(__DIR__) . '/app/Models/Role.php';
require_once dirname(__DIR__) . '/app/Models/User.php';

use App\Models\Role;
use App\Models\User;

echo "=================================================================\n";
echo " SmartLaundry AI - Authentication & RBAC Verification Test\n";
echo "=================================================================\n\n";

$roleModel = new Role();
$userModel = new User();

// Test 1: Verify Roles in DB
echo "[Test 1] Verify Roles:\n";
$roles = $roleModel->allRoles();
foreach ($roles as $r) {
    echo "  - Role #{$r['id']}: {$r['name']} ({$r['display_name']})\n";
}
assert(count($roles) === 6, "Expected 6 roles");
echo "  => Test 1 PASSED!\n\n";

// Test 2: Verify Users and Password Hashing
echo "[Test 2] Verify Users & Passwords:\n";
$testEmails = [
    'owner@smartlaundry.id' => 'owner_platform',
    'admin.owner@smartlaundry.id' => 'admin_owner',
    'owner.cleanwash@smartlaundry.id' => 'owner_toko',
    'admin.cleanwash@smartlaundry.id' => 'admin_toko',
    'kurir.budi@smartlaundry.id' => 'kurir',
    'customer.ani@gmail.com' => 'customer'
];

foreach ($testEmails as $email => $expectedRole) {
    $user = $userModel->findByEmail($email);
    assert($user !== null, "User not found for {$email}");
    assert($user['role_name'] === $expectedRole, "Role mismatch for {$email}");
    $validPass = $userModel->verifyPassword('password123', $user['password']);
    assert($validPass === true, "Password verification failed for {$email}");
    echo "  - {$user['name']} ({$user['role_display']}) -> OK! Target: " . get_dashboard_url_by_role($user['role_name']) . "\n";
}
echo "  => Test 2 PASSED!\n\n";

// Test 3: Customer Registration Flow
echo "[Test 3] Test Customer Registration Model:\n";
$testRegEmail = 'test.customer.' . time() . '@example.com';
$newUserId = $userModel->createCustomer([
    'name' => 'Budi Tester',
    'email' => $testRegEmail,
    'phone' => '081299988877',
    'password' => 'secret123'
]);
$newUser = $userModel->findWithRole((int)$newUserId);
assert($newUser !== null, "Created user not found");
assert($newUser['role_name'] === 'customer', "New user role must be customer");
assert($userModel->verifyPassword('secret123', $newUser['password']) === true, "Password check failed");
echo "  - Registered: {$newUser['name']} ({$newUser['email']}) as {$newUser['role_name']} (ID: {$newUserId})\n";

// Clean up test user
$userModel->delete($newUserId);
echo "  - Cleaned up test user successfully.\n";
echo "  => Test 3 PASSED!\n\n";

// Test 4: Verification that Demo Role Switcher has been completely removed
echo "[Test 4] Verify Demo Role Switcher Removal:\n";

// 4a. Check that User model no longer has getAllDemoUsers method
assert(!method_exists($userModel, 'getAllDemoUsers'), "getAllDemoUsers must be removed from User model");
echo "  - User::getAllDemoUsers() correctly removed from model.\n";

// 4b. Check that AuthController no longer has demoSwitch method
require_once dirname(__DIR__) . '/app/Core/Controller.php';
require_once dirname(__DIR__) . '/app/Controllers/AuthController.php';
assert(!method_exists(\App\Controllers\AuthController::class, 'demoSwitch'), "demoSwitch must be removed from AuthController");
echo "  - AuthController::demoSwitch() correctly removed from controller.\n";

// 4c. Check navbar and login views do not contain 'Demo Role Switcher' or cheatsheet
$navbarContent = file_get_contents(dirname(__DIR__) . '/app/Views/layouts/navbar.php');
assert(strpos($navbarContent, 'Demo Role Switcher') === false, "Navbar must not contain 'Demo Role Switcher'");
assert(strpos($navbarContent, 'demo-switch') === false, "Navbar must not contain 'demo-switch'");
echo "  - Navbar view is clean (no Demo Role Switcher / demo-switch).\n";

$loginContent = file_get_contents(dirname(__DIR__) . '/app/Views/auth/login.php');
assert(strpos($loginContent, 'Akun Uji Coba') === false, "Login view must not contain 'Akun Uji Coba'");
assert(strpos($loginContent, 'demo-switch') === false, "Login view must not contain 'demo-switch'");
echo "  - Login view is clean (no testing cheatsheet box).\n";
echo "  => Test 4 PASSED!\n\n";

// Test 5: Verify Old Demo Switch URL returns 404
echo "[Test 5] Verify /auth/demo-switch/{id} returns 404:\n";
require_once dirname(__DIR__) . '/app/Core/Request.php';
require_once dirname(__DIR__) . '/app/Core/Response.php';
require_once dirname(__DIR__) . '/app/Core/Router.php';
require_once dirname(__DIR__) . '/app/Core/View.php';

// Test 5a: Guest user visits old demo-switch URL
$req1 = new \App\Core\Request();
$res1 = new \App\Core\Response();
$router1 = new \App\Core\Router($req1, $res1);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/auth/demo-switch/1';
unset($_SESSION['user']);

ob_start();
$router1->dispatch();
ob_end_clean();
assert($res1->getStatusCode() === 404, "Expected 404 for guest accessing /auth/demo-switch/1, got " . $res1->getStatusCode());
echo "  - Guest request to /auth/demo-switch/1 returned 404.\n";

// Test 5b: Authenticated user visits old demo-switch URL
$req2 = new \App\Core\Request();
$res2 = new \App\Core\Response();
$router2 = new \App\Core\Router($req2, $res2);
$_SESSION['user'] = ['id' => 9, 'role' => 'customer'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/auth/demo-switch/1';

ob_start();
$router2->dispatch();
ob_end_clean();
assert($res2->getStatusCode() === 404, "Expected 404 for logged-in user accessing /auth/demo-switch/1, got " . $res2->getStatusCode());
echo "  - Authenticated request to /auth/demo-switch/1 returned 404.\n";
echo "  => Test 5 PASSED!\n\n";

// Test 6: Verify manual login credential check & role redirection for all 6 roles
echo "[Test 6] Verify Manual Form Login Flow for 6 Roles:\n";
$roleRedirects = [
    'owner@smartlaundry.id' => ['role' => 'owner_platform', 'redirect' => 'dashboard/platform'],
    'admin.owner@smartlaundry.id' => ['role' => 'admin_owner', 'redirect' => 'dashboard/platform'],
    'owner.cleanwash@smartlaundry.id' => ['role' => 'owner_toko', 'redirect' => 'dashboard/store'],
    'admin.cleanwash@smartlaundry.id' => ['role' => 'admin_toko', 'redirect' => 'dashboard/store'],
    'kurir.budi@smartlaundry.id' => ['role' => 'kurir', 'redirect' => 'dashboard/courier'],
    'customer.ani@gmail.com' => ['role' => 'customer', 'redirect' => 'dashboard/customer'],
];

foreach ($roleRedirects as $email => $expected) {
    $u = $userModel->findByEmail($email);
    assert($u !== null, "User {$email} must exist");
    assert($userModel->verifyPassword('password123', $u['password']) === true, "Password verification failed");
    $target = get_dashboard_url_by_role($u['role_name']);
    assert(strpos($target, $expected['redirect']) !== false, "Dashboard redirect mismatch for {$email}");
    echo "  - Role {$expected['role']} ({$email}): Verified credentials -> Target: {$target}\n";
}
echo "  => Test 6 PASSED!\n\n";

echo "=================================================================\n";
echo " ALL TESTS PASSED SUCCESSFULLY (100%)\n";
echo "=================================================================\n";
