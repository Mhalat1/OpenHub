<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserController extends AbstractController
{

    private EntityManagerInterface $manager;
    private $user;


    public function __construct(EntityManagerInterface $manager)
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

    
}
