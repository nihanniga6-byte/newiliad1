<?php
/**
 * Admin Dashboard Controller
 * 
 * Handles admin dashboard operations.
 * 
 * @package App\Controllers\Admin
 */

class AdminDashboardController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->userModel = new User();
        $this->activityModel = new ActivityLog();
        $this->sessionModel = new Session();
    }

    /**
     * Display admin dashboard
     */
    public function index(): void
    {
        $user = current_user();
        
        $stats = $this->userModel->getDashboardStats();
        $activeSessions = $this->sessionModel->getActiveCount();
        $recentUsers = $this->userModel->getRecent(5);
        $recentActivity = $this->activityModel->getRecent(10);

        $this->view('admin.dashboard', [
            'user' => $user,
            'stats' => $stats,
            'active_sessions' => $activeSessions,
            'recent_users' => $recentUsers,
            'recent_activity' => $recentActivity,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Get dashboard stats (API)
     */
    public function getStats(): void
    {
        $stats = $this->userModel->getDashboardStats();
        $activeSessions = $this->sessionModel->getActiveCount();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => array_merge($stats, ['active_sessions' => $activeSessions])
        ]);
    }
}
