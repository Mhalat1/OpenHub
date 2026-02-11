<?php
// src/Security/LoginFailureHandler.php
namespace App\Security;

use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    // Ajoute ": ?Response" à la fin pour correspondre à l'interface
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey(),
        ], 401);
    }
}
