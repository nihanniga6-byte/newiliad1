<?php
/**
 * Admin Settings Controller
 * 
 * Handles site settings management.
 * 
 * @package App\Controllers\Admin
 */

class SettingsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->settingModel = new SiteSetting();
    }

    /**
     * Display settings page
     */
    public function index(): void
    {
        $user = current_user();
        $settings = $this->settingModel->getAll();

        $this->view('admin.settings', [
            'user' => $user,
            'settings' => $settings,
            'success' => Session::getFlash('success'),
            'error' => Session::getFlash('error')
        ]);
    }

    /**
     * Update settings
     */
    public function update(): void
    {
        $user = current_user();

        $siteName = $this->input('site_name');
        $siteEmail = $this->input('site_email');
        $sitePhone = $this->input('site_phone');
        $siteAddress = $this->input('site_address');
        $maintenanceMode = $this->input('maintenance_mode') === 'on';

        $errors = [];

        if (empty($siteName)) $errors[] = 'Site name is required';
        if (empty($siteEmail) || !filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid site email is required';
        }

        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            redirect('/admin/settings');
            return;
        }

        $this->settingModel->set('site_name', $siteName);
        $this->settingModel->set('site_email', $siteEmail);
        $this->settingModel->set('site_phone', $sitePhone);
        $this->settingModel->set('site_address', $siteAddress);
        $this->settingModel->set('maintenance_mode', $maintenanceMode);

        ActivityLogger::log($user['id'], 'updated_settings');
        Session::flash('success', 'Settings updated successfully');
        redirect('/admin/settings');
    }

    /**
     * Clear cache
     */
    public function clearCache(): void
    {
        $user = current_user();
        
        $cache = new Cache();
        $cache->clear();

        ActivityLogger::log($user['id'], 'cleared_cache');
        Session::flash('success', 'Cache cleared successfully');
        redirect('/admin/settings');
    }
}
