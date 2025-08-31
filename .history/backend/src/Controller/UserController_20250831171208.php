<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;

final class UserController extends AbstractController
{
    private $manager;
    private $user;
    public function __construct($manager, $user)
    {
        $this->manager = $manager;
        $this->user = $user;
    }




    #[Route('/user', name: 'app_user')]
    public function index(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $email=$data['email'];
        $password=$data['password'];

        $email_exists = $this->user->findOneByEmail(['email' => $email]);

        if (!$email_exists) {
            return $this->json(['message' => 'Email déjà utilisé, veuillez en choisir un autre'], 400);
        }
        else {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword(sha1($password));
            
            $this->manager->persist($user);
            $this->manager->flush();
            return $this->json(['message' => 'Utilisateur créé avec succès'], 201);
        }


        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }

    #[Route('/getAllUser', name: 'get_allusers', methods: ['GET'])]
    public function getAllUsers(): Response
    {
        $users = $this->user->findAll();
        
        return 

        foreach ($users as $user) {
            $data[] = [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                // Add other user properties as needed
            ];
        }

        return $this->json($data);

    }
}
