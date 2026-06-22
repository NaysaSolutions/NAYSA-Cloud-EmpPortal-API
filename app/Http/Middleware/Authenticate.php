<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    // protected function redirectTo(Request $request): ?string
    // {
    //     return $request->expectsJson() ? null : route('login');
    // }

//     protected function redirectTo(Request $request): ?string
// {
//     // If the request is for an API path or expects JSON, return null (401 error)
//     if ($request->expectsJson() || $request->is('api/*')) {
//         return null;
//     }

//     // return route('login');
//     return route('loginEmp');
// }

protected function redirectTo(Request $request): ?string
{
    // If the request is for an API path, return null to trigger a 401 JSON error 
    // instead of a 302 Redirect to a GET route.
    if ($request->expectsJson() || $request->is('api/*')) {
        return null;
    }

    return route('login'); 
}


}
