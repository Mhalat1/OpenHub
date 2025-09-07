<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Persistence\ManagerRegistry;

use App\Entity\Competence;
use App\Entity\Contribution;
use App\Entity\Projet;
use App\Entity\Message;

final class ProfilController extends AbstractController
{

    #[Route('/', name: 'app_profil')]
    public function index(
        ManagerRegistry $doctrine
    ): Response
    {
        $utilisateurs = $doctrine->getRepository(Utilisateur::class)->findAll();
        $competence = $doctrine->getRepository(Competence::class)->findAll();
        $contribution = $doctrine->getRepository(Contribution::class)->findAll();
        $projet = $doctrine->getRepository(Projet::class)->findAll();
        $message = $doctrine->getRepository(Message::class)->findAll();
        $user = $this -> getUser();


foreach ($utilisateurs as $utilisateur) {
    if ($utilisateur->getCourriel() === $user->getUserIdentifier()) {
        $nom = $utilisateur->getNom();
        $prenom = $utilisateur->getPrenom();
        $courriel = $utilisateur->getCourriel();
        $telephone = $utilisateur->getTelephone();
        $motDePasse = $utilisateur->getMotDePasse();
        $ApiToken = $utilisateur->getApiToken();
    }
}



        //dd( $utilisateur, $competence, $contribution, $projet, $message);
        return $this->render('profil/index.html.twig', [
            'nom' => $nom,
            'prenom' => $prenom,
            'courriel' => $courriel,
            'telephone' => $telephone,
            'token'=>  $ApiToken,
            'motDePasse' => $motDePasse,
            'competences' => $competence,
            'contributions' => $contribution,
            'projets' => $projet,
            'messages' => $message,
        ]);
    }
}

