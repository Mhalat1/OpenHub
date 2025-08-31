<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

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
            return $this->json(['message' => 'Email does not exist'], 400);
        }


        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }
}
