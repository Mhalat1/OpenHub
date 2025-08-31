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

final class UserController extends AbstractController
{

    private EntityManagerInterface $manager;
    private $user;


    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
        $this->user = $this->manager->getRepository(User::class);
    }




    #[Route('/userCreate', name: 'user_create', methods: ['POST'])]
    public function userCreate(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $email=$data['email'];
        $password=$data['password'];

        $email_exists = $this->user->findOneByEmail($email);

        if (!$email_exists) {
                        $user = new User();
            $user->setEmail($email);
            $user->setPassword(sha1($password));
            
            $this->manager->persist($user);
            $this->manager->flush();
            return new JsonResponse
            (
                [
                'status' => true,
                'message' => 'Utilisateur créé avec succès'
                ]

                );        }
        else {
            return new JsonResponse
            (
                [
                'status' => false,
                'message' => 'cet email est déjà utilisé'
                ]

                );
        }


        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    #[Route('/getAllUser', name: 'get_allusers', methods: ['GET'])]
    public function getAllUsers(): Response
    {
        $users = $this->user->findAll();
        
        return $this->json($users);

    }

    
}
