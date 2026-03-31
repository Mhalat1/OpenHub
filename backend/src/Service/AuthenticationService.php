<?php

namespace App\Service;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
interface AuthenticationServiceInterface
{
    public function getLastUsername(): ?string;
    public function getLastError(): ?AuthenticationException;
}

//AuthenticationServiceInterface sert à :
//✅ Abstraire la gestion des erreurs d'authentification
//✅ Faciliter les tests unitaires
//✅ Découpler le contrôleur de l'implémentation Symfony
//✅ Centraliser la logique de récupération des erreurs de connexion


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

