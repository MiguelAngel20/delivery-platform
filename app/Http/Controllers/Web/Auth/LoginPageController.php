<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class LoginPageController extends Controller
{
    public function customer(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Iniciar sesión',
            'description' => 'Accede a tu cuenta de cliente',
            'submitLabel' => 'Entrar',
            'portal' => 'customer',
            'loginRouteName' => 'login',
        ]);
    }

    public function admin(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Acceso administración',
            'description' => 'Ingresa con tu cuenta de sistema',
            'submitLabel' => 'Entrar',
            'portal' => 'admin',
            'loginRouteName' => 'admin.login',
        ]);
    }

    public function business(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Acceso negocio',
            'description' => 'Ingresa con tu cuenta de empresa',
            'submitLabel' => 'Entrar',
            'portal' => 'business',
            'loginRouteName' => 'business.login',
        ]);
    }

    public function driver(Request $request): Response
    {
        return $this->render($request, [
            'title' => 'Acceso repartidor',
            'description' => 'Ingresa con tu cuenta de repartidor',
            'submitLabel' => 'Entrar',
            'portal' => 'driver',
            'loginRouteName' => 'driver.login',
        ]);
    }

    /**
     * @param  array{title: string, description: string, submitLabel: string, portal: string, loginRouteName: string}  $config
     */
    private function render(Request $request, array $config): Response
    {
        $request->session()->put('login_portal', $config['loginRouteName']);

        cookie()->queue(cookie(
            'login_portal',
            $config['loginRouteName'],
            60 * 24 * 14,
        ));

        return Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'title' => $config['title'],
            'description' => $config['description'],
            'submitLabel' => $config['submitLabel'],
            'portal' => $config['portal'],
        ]);
    }
}
