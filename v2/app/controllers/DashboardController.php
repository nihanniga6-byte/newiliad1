<?php
/**
 * Dashboard Controller (User)
 * 
 * Handles user dashboard operations.
 * 
 * @package App\Controllers
 */

class DashboardController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
        $this->profileModel = new UserProfile();
    }

    /**
     * Display user dashboard
     */
    public function index(): void
    {
        $user = current_user();
        $profile = $this->profileModel->getByUserId($user['id']);

        $this->view('dashboard.index', [
            'user' => $user,
            'profile' => $profile,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Display profile page
     */
    public function profile(): void
    {
        $user = current_user();
        $profile = $this->profileModel->getByUserId($user['id']);

        $this->view('dashboard.profile', [
            'user' => $user,
            'profile' => $profile,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(): void
    {
        $user = current_user();
        $fullname = $this->input('fullname');
        $email = $this->input('email');
        $phone = $this->input('phone');
        $address = $this->input('address');

        $errors = [];

        if (empty($fullname)) $errors[] = 'Full name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';

        // Check if email is taken by another user
        if (empty($errors) && $email !== $user['email']) {
            $existingUser = $this->userModel->findByEmail($email);
            if ($existingUser && $existingUser['id'] !== $user['id']) {
                $errors[] = 'Email address already in use';
            }
        }

        if (!empty($errors)) {
            $this->view('dashboard.profile', [
                'user' => $user,
                'errors' => $errors,
                'old' => ['fullname' => $fullname, 'email' => $email, 'phone' => $phone, 'address' => $address]
            ]);
            return;
        }

        // Update user
        $this->userModel->updateUser($user['id'], [
            'fullname' => $fullname,
            'email' => $email
        ]);

        // Update profile
        $this->profileModel->save($user['id'], [
            'phone' => $phone,
            'address' => $address
        ]);

        Session::flash('success', 'Profile updated successfully');
        redirect('/dashboard/profile');
    }

    /**
     * Display change password page
     */
    public function password(): void
    {
        $user = current_user();

        $this->view('dashboard.password', [
            'user' => $user,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(): void
    {
        $user = current_user();
        $currentPassword = $this->input('current_password');
        $newPassword = $this->input('new_password');
        $confirmPassword = $this->input('confirm_password');

        $errors = [];

        if (empty($currentPassword)) $errors[] = 'Current password is required';
        if (empty($newPassword)) $errors[] = 'New password is required';
        if (strlen($newPassword) < 8) $errors[] = 'Password must be at least 8 characters';
        if ($newPassword !== $confirmPassword) $errors[] = 'Passwords do not match';

        // Verify current password
        if (empty($errors)) {
            $fullUser = $this->userModel->find($user['id']);
            if (!password_verify($currentPassword, $fullUser['password_hash'])) {
                $errors[] = 'Current password is incorrect';
            }
        }

        if ($newPassword === $currentPassword && empty($errors)) {
            $errors[] = 'New password must be different from current password';
        }

        if (!empty($errors)) {
            $this->view('dashboard.password', [
                'user' => $user,
                'errors' => $errors
            ]);
            return;
        }

        // Change password
        $this->userModel->changePassword($user['id'], $newPassword);

        // Log activity
        ActivityLogger::log($user['id'], 'password_changed');

        Session::flash('success', 'Password changed successfully');
        redirect('/dashboard/password');
    }

    /**
     * Handle avatar upload
     */
    public function uploadAvatar(): void
    {
        $user = current_user();
        $fileHandler = new FileHandler();
        
        $uploaded = $fileHandler->upload('avatar', 'avatars');
        
        if ($uploaded) {
            // Delete old avatar if exists
            $profile = $this->profileModel->getByUserId($user['id']);
            if ($profile && $profile['avatar']) {
                $fileHandler->delete($profile['avatar']);
            }

            // Save new avatar
            $this->profileModel->updateAvatar($user['id'], $uploaded['relative_path']);
            
            Session::flash('success', 'Avatar updated successfully');
        } else {
            $errors = $fileHandler->getErrors();
            Session::flash('error', $errors[0] ?? 'Failed to upload avatar');
        }

        redirect('/dashboard/profile');
    }
}
