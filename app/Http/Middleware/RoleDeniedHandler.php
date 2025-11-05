<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\Exceptions\UnauthorizedException;

class RoleDeniedHandler
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (UnauthorizedException $e) {
            // Puedes personalizar el comportamiento aquí 👇

            // 1️⃣ Para peticiones normales (HTML)
            if ($request->expectsHtml()) {
                return redirect('/')
                    ->with('error', '🚫 No tienes permisos para acceder a esta sección.');
            }

            // 2️⃣ Para peticiones JSON / API
            return response()->json([
                'message' => 'Acceso denegado: no tienes los roles necesarios.',
                'required_roles' => $e->getRequiredRoles(),
            ], 403);
        }
    }
}
