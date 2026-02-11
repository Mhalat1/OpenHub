<?php

namespace App\Service;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
interface AuthenticationServiceInterface
{
    public function getLastUsername(): ?string;
    public function getLastError(): ?AuthenticationException;
}

class AuthenticationService implements AuthenticationServiceInterface
{
    public function __construct(private AuthenticationUtils $authenticationUtils) {}

    public function getLastUsername(): ?string
    {
        return $this->authenticationUtils->getLastUsername();
    }

    public function getLastError(): ?AuthenticationException
    {
        return $this->authenticationUtils->getLastAuthenticationError();
    }
}

