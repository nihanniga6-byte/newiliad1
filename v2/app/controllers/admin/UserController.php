<?php
/**
 * Admin User Controller
 * 
 * Handles user management for admins.
 * 
 * @package App\Controllers\Admin
 */

class UserController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
        $this->profileModel = new UserProfile();
    }

    /**
     * List all users
     */
    public function index(): void
    {
        $user = current_user();
        $page = $this->input('page', 1, 'int');
        $search = $this->input('search');

        $result = $this->userModel->getUsers($page, 10, $search);

        $this->view('admin.users', [
            'user' => $user,
            'users' => $result['users'],
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total_pages' => $result['total_pages']
            ],
            'search' => $search,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Show create user form
     */
    public function create(): void
    {
        $user = current_user();

        $this->view('admin.create-user', [
            'user' => $user,
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Store new user
     */
    public function store(): void
    {
        $user = current_user();
        
        $fullname = $this->input('fullname');
        $email = $this->input('email');
        $password = $this->input('password');
        $role = $this->input('role', 'user');
        $status = $this->input('status', 'active');

        $errors = [];

        if (empty($fullname)) $errors[] = 'Full name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        if (empty($password)) $errors[] = 'Password is required';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
        if (!in_array($role, ['user', 'admin'])) $errors[] = 'Invalid role';

        // Check if email exists
        if (empty($errors)) {
            $existingUser = $this->userModel->findByEmail($email);
            if ($existingUser) {
                $errors[] = 'Email address already registered';
            }
        }

        if (!empty($errors)) {
            $this->view('admin.create-user', [
                'user' => $user,
                'errors' => $errors,
                'old' => ['fullname' => $fullname, 'email' => $email, 'role' => $role, 'status' => $status]
            ]);
            return;
        }

        $userId = $this->userModel->createUser([
            'fullname' => $fullname,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'email_verified' => 1,
            'status' => $status
        ]);

        if ($userId) {
            $this->profileModel->save($userId, []);
            
            ActivityLogger::log($user['id'], "created_user:{$userId}");
            Session::flash('success', 'User created successfully');
            redirect('/admin/users');
        } else {
            $this->view('admin.create-user', [
                'user' => $user,
                'error' => 'Failed to create user',
                'old' => ['fullname' => $fullname, 'email' => $email, 'role' => $role, 'status' => $status]
            ]);
        }
    }

    /**
     * Show edit user form
     */
    public function edit(int $id): void
    {
        $user = current_user();
        $targetUser = $this->userModel->find($id);

        if (!$targetUser) {
            Session::flash('error', 'User not found');
            redirect('/admin/users');
            return;
        }

        $profile = $this->profileModel->getByUserId($id);

        $this->view('admin.edit-user', [
            'user' => $user,
            'targetUser' => $targetUser,
            'profile' => $profile,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Update user
     */
    public function update(int $id): void
    {
        $user = current_user();
        $targetUser = $this->userModel->find($id);

        if (!$targetUser) {
            Session::flash('error', 'User not found');
            redirect('/admin/users');
            return;
        }

        $fullname = $this->input('fullname');
        $email = $this->input('email');
        $role = $this->input('role');
        $status = $this->input('status');
        $phone = $this->input('phone');
        $address = $this->input('address');

        $errors = [];

        if (empty($fullname)) $errors[] = 'Full name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        if (!in_array($role, ['user', 'admin'])) $errors[] = 'Invalid role';
        if (!in_array($status, ['active', 'banned'])) $errors[] = 'Invalid status';

        // Check if email is taken by another user
        if (empty($errors) && $email !== $targetUser['email']) {
            $existingUser = $this->userModel->findByEmail($email);
            if ($existingUser && $existingUser['id'] !== $id) {
                $errors[] = 'Email address already in use';
            }
        }

        if (!empty($errors)) {
            $this->view('admin.edit-user', [
                'user' => $user,
                'targetUser' => $targetUser,
                'errors' => $errors,
                'old' => ['fullname' => $fullname, 'email' => $email, 'role' => $role, 'status' => $status, 'phone' => $phone, 'address' => $address]
            ]);
            return;
        }

        $this->userModel->updateUser($id, [
            'fullname' => $fullname,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ]);

        $this->profileModel->save($id, [
            'phone' => $phone,
            'address' => $address
        ]);

        ActivityLogger::log($user['id'], "updated_user:{$id}");
        Session::flash('success', 'User updated successfully');
        redirect("/admin/users/{$id}/edit");
    }

    /**
     * Delete user
     */
    public function delete(int $id): void
    {
        $user = current_user();

        // Prevent self-deletion
        if ($user['id'] === $id) {
            Session::flash('error', 'You cannot delete your own account');
            redirect('/admin/users');
            return;
        }

        $targetUser = $this->userModel->find($id);
        if (!$targetUser) {
            Session::flash('error', 'User not found');
            redirect('/admin/users');
            return;
        }

        // Delete user profile first
        $this->profileModel->deleteProfile($id);

        // Delete user
        $this->userModel->deleteUser($id);

        ActivityLogger::log($user['id'], "deleted_user:{$id}");
        Session::flash('success', 'User deleted successfully');
        redirect('/admin/users');
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(int $id): void
    {
        $user = current_user();
        $targetUser = $this->userModel->find($id);

        if (!$targetUser) {
            Session::flash('error', 'User not found');
            redirect('/admin/users');
            return;
        }

        // Prevent self-ban
        if ($user['id'] === $id) {
            Session::flash('error', 'You cannot ban your own account');
            redirect('/admin/users');
            return;
        }

        $newStatus = $targetUser['status'] === 'active' ? 'banned' : 'active';
        $this->userModel->updateUser($id, ['status' => $newStatus]);

        $action = $newStatus === 'banned' ? 'banned_user' : 'unbanned_user';
        ActivityLogger::log($user['id'], "{$action}:{$id}");
        $statusWord = $newStatus === 'banned' ? 'banned' : 'unbanned';
        Session::flash('success', "User {$statusWord} successfully");
        redirect('/admin/users');
    }

    /**
     * Search users (AJAX)
     */
    public function search(): void
    {
        $query = $this->input('q');
        
        if (strlen($query) < 2) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }

        $users = $this->userModel->search($query, 10);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $users]);
    }
}
