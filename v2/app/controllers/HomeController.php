<?php
/**
 * Home Controller
 * 
 * Handles public-facing pages.
 * 
 * @package App\Controllers
 */

class HomeController extends Controller
{
    /**
     * Display homepage
     */
    public function index(): void
    {
        $user = current_user();
        $this->view('home.index', [
            'user' => $user
        ]);
    }
}
