<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;


final class UserController extends AbstractController
{

    private EntityManagerInterface $manager;
    private $user;
    private $security;


    public function __construct(EntityManagerInterface $manager,)
    {
        $this->manager = $manager;
        $this->user = $this->manager->getRepository(User::class);
    }


#[Route('/api/userCreate', name: 'user_create', methods: ['POST'])]
public function userCreate(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $email = $data['email'] ?? null;
    $password = $data['password'] ?? null;
    $prenom = $data['prenom'] ?? null;
    $nom = $data['nom'] ?? null;
    $debutDispo = $data['debutDispo'] ?? null;
    $finDispo = $data['finDispo'] ?? null;

    if (!$email || !$password) {
        return new JsonResponse([
            'status' => false,
            'message' => 'Email et mot de passe requis'
        ], 400);
    }

    $emailExists = $this->user->findOneByEmail($email);

    if ($emailExists) {
        return new JsonResponse([
            'status' => false,
            'message' => 'Cet email est déjà utilisé'
        ], 409);
    }

    $user = new User();
    $user->setEmail($email);
    $user->setPassword($passwordHasher->hashPassword($user, $password));
    $user->setPrenom($prenom);
    $user->setNom($nom);
    $user->setRoles(['ROLE_USER']);
    $user->setDebutDispo($debutDispo ? new \DateTimeImmutable($debutDispo) : null);
    $user->setFinDispo($finDispo ? new \DateTimeImmutable($finDispo) : null);

    $this->manager->persist($user);
    $this->manager->flush();

    return new JsonResponse([
        'status' => true,
        'message' => 'Utilisateur créé avec succès'
    ], 201);
}


    #[Route('/api/getAllUsers', name: 'get_allusers', methods: ['GET'])]
    public function getAllUsers(): Response
    {
        $users = $this->user->findAll();
        
        return $this->json($users);
    }

#[Route('api/getConnectedUser', name: 'get_connected_user', methods: ['GET'])]
public function getConnectedUser(Request $request, Security $security): Response
{
    // Récupérer l'utilisateur connecté
    $user = $security->getUser();
    
    if (!$user) {
        return $this->json(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
    }

    // Récupérer le jeton JWT dans les headers de la requête
    $authorizationHeader = $request->headers->get('Authorization');
    
    if ($authorizationHeader) {
        // Le jeton JWT est généralement dans le format "Bearer <token>"
        $jwt = str_replace('Bearer ', '', $authorizationHeader);  // Extraire le token
    } else {
        return $this->json(['error' => 'JWT Token not found'], Response::HTTP_UNAUTHORIZED);
    }

    // Pour déboguer, tu peux afficher le JWT récupéré (pas recommandé en production)
     dd($jwt);
    
    // Retourner les informations de l'utilisateur connecté
    return $this->json([
        'jwt' => $jwt,  // Affichage du JWT pour test (non sécurisé à exposer en prod)
    ]);
}

}
