<?php

namespace App\Controller;


use App\Form\UtilisateurType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Contributeur; 

class InscriptionController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $utilisateur = new Contributeur();

        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Encodage du mot de passe
            $motDePasseHashee = $passwordHasher->hashPassword(
                $utilisateur,
                $form->get('motDePasse')->getData()
            );
            $utilisateur->setMotDePasse($motDePasseHashee);

            $entityManager->persist($utilisateur);
            $entityManager->flush();

            // Redirection vers login ou autre
            return $this->redirectToRoute('app_login');
        }

        return $this->render('inscription/index.html.twig', [
            'inscriptionForm' => $form->createView(),
        ]);
    }
}
