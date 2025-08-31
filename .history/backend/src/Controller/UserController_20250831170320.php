<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
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
    public function index(Request): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }
}
