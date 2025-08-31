<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UtilisateurController extends AbstractController
{
    #[Route('api/debug-auth', name: 'app_debug_auth')]
    public function debugAuth()
    {
        $user = $this->getUser();
        
        return $this->json([
            'authenticated' => $user !== null,
            'username' => $user?->getUserIdentifier(),
            'roles' => $user?->getRoles() ?? [],
            'has_contrib_role' => $user ? in_array('ROLE_CONTRIB', $user->getRoles()) : false
        ]);
    }

    #[Route('api/me', name: 'app_me')]
    #[IsGranted('ROLE_CONTRIB')]
    public function me()
    {
        return $this->json([$this->getUser()]);
    }
}