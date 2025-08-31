<?php


namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Utilisateur;

class  UtilisateurController extends AbstractController
{
    #[Route('api/me', name: 'app_me')]
    #[IsGranted('ROLE_CONTRIB')]
    public function me()
    {
        return $this->json([$this->()]);


    }
}