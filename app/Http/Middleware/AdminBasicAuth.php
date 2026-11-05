<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = (string) config('hookrelay.admin.password');

        if ($password === '') {
            return $next($request);
        }

        $userOk = hash_equals((string) config('hookrelay.admin.user'), (string) $request->getUser());
        $passwordOk = hash_equals($password, (string) $request->getPassword());

        if (!$userOk || !$passwordOk) {
            return response('Authentication required.', 401, ['WWW-Authenticate' => 'Basic realm="hookrelay"']);
        }

        return $next($request);
    }
}
