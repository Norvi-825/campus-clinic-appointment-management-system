<?php
/**
 * Authentication & Profile Service
 *
 * Encapsulates student registration, login verification, profile updates,
 * and password changes using PHP PDO prepared statements and password_hash().
 */

declare(strict_types=1);

class AuthService
{
    /**
     * Registers a new student account after strict server-side validation.
     *
     * @return array{success: bool, message: string, user_id?: int, errors?: array<string, string>}
     */
    public static function registerStudent(PDO $pdo, array $input): array
    {
        $fullName = trim((string) ($input['full_name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        $errors = [];

        if ($fullName === '' || mb_strlen($fullName) < 3 || mb_strlen($fullName) > 120) {
            $errors['full_name'] = 'Full name must be between 3 and 120 characters.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,25}$/', $phone)) {
            $errors['phone'] = 'Phone number may only contain digits, spaces, +, -, and parentheses.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirmPassword) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Please correct the highlighted registration fields.',
                'errors' => $errors,
            ];
        }

        // Prevent duplicate email addresses
        $checkStmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $checkStmt->execute(['email' => $email]);
        if ($checkStmt->fetch()) {
            return [
                'success' => false,
                'message' => 'An account with this email address already exists.',
                'errors' => ['email' => 'This email address is already registered.'],
            ];
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insertStmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, phone, role, account_status)
             VALUES (:full_name, :email, :password_hash, :phone, "student", "active")'
        );
        $insertStmt->execute([
            'full_name' => $fullName,
            'email' => $email,
            'password_hash' => $passwordHash,
            'phone' => $phone !== '' ? $phone : null,
        ]);

        return [
            'success' => true,
            'message' => 'Registration successful! You can now log in to book campus clinic appointments.',
            'user_id' => (int) $pdo->lastInsertId(),
        ];
    }

    /**
     * Authenticates a user by email and password.
     *
     * @return array{success: bool, message: string, user?: array<string, mixed>}
     */
    public static function authenticate(PDO $pdo, string $email, string $password): array
    {
        $cleanEmail = strtolower(trim($email));

        if ($cleanEmail === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Email address and password are required.',
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, email, password_hash, phone, role, account_status
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $cleanEmail]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid email address or password.',
            ];
        }

        if ($user['account_status'] !== 'active') {
            return [
                'success' => false,
                'message' => 'Your account has been deactivated. Please contact the USTED University Clinic Administrator.',
            ];
        }

        unset($user['password_hash']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user,
        ];
    }

    /**
     * Updates permitted profile details (full name and phone).
     *
     * @return array{success: bool, message: string}
     */
    public static function updateProfile(PDO $pdo, int $userId, string $fullName, string $phone): array
    {
        $fullName = trim($fullName);
        $phone = trim($phone);

        if ($fullName === '' || mb_strlen($fullName) < 3 || mb_strlen($fullName) > 120) {
            return ['success' => false, 'message' => 'Full name must be between 3 and 120 characters.'];
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,25}$/', $phone)) {
            return ['success' => false, 'message' => 'Please provide a valid phone number.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET full_name = :full_name, phone = :phone WHERE user_id = :uid'
        );
        $stmt->execute([
            'full_name' => $fullName,
            'phone' => $phone !== '' ? $phone : null,
            'uid' => $userId,
        ]);

        if (isset($_SESSION['full_name']) && (int) ($_SESSION['user_id'] ?? 0) === $userId) {
            $_SESSION['full_name'] = $fullName;
        }

        return ['success' => true, 'message' => 'Your profile information has been updated.'];
    }

    /**
     * Verifies the current password and updates to a new securely hashed password.
     *
     * @return array{success: bool, message: string}
     */
    public static function changePassword(
        PDO $pdo,
        int $userId,
        string $currentPassword,
        string $newPassword,
        string $confirmPassword
    ): array {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            return ['success' => false, 'message' => 'All password fields are required.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long.'];
        }

        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'New password and confirmation do not match.'];
        }

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
            return ['success' => false, 'message' => 'Your current password is incorrect.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE user_id = :uid');
        $updateStmt->execute([
            'hash' => $newHash,
            'uid' => $userId,
        ]);

        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
}
