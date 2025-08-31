<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UtilisateurController extends AbstractController
{
    #[Route('api/me', name: 'app_me')]
    // #[IsGranted('ROLE_CONTRIB')] // Temporarily commented out
    public function me()
    {
        $user = $this->getUser();
        
        // Add null check since no authentication is required now
        if (!$user) {
            return $this->json(['message' => 'No user authenticated'], 401);
        }
        
        return $this->json([$user]);
    }


    #[Route('api/debug', name: 'app_debug')]
    public function debug()
    {
        $user = $this->getUser();
        
        return $this->json([
            'user_exists' => $user !== null,
            'user_class' => $user ? get_class($user) : null,
            'user_identifier' => $user?->getUserIdentifier(),
            'user_roles' => $user?->getRoles() ?? [],
            'security_token' => $this->container->get('security.token_storage')->getToken() ? 'exists' : 'null'
        ]);
    }
}