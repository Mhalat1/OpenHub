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
}