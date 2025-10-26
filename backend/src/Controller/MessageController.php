<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    #[Route('/api/getConnectedUser', name: 'get_connected_user', methods: ['GET'])]
    public function getConnectedUser(): JsonResponse
    {
        try {
            // Utiliser $this->getUser() au lieu de Security
            $user = $this->getUser();

            if (!$user) {
                return new JsonResponse([
                    'message' => 'User not authenticated',
                    'debug' => 'No user found in security context'
                ], 401);
            }

            if (!$user instanceof User) {
                return new JsonResponse([
                    'message' => 'Invalid user type',
                    'debug' => 'User is not an instance of App\Entity\User'
                ], 500);
            }

            // Récupérer les conversations
            $conversations = [];
            foreach ($user->getConversations() as $conversation) {
                $conversations[] = [
                    'id' => $conversation->getId(),
                ];
            }

            return new JsonResponse([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d'),
                'userData' => $conversations,
            ]);

        } catch (\Throwable $e) {
            return new JsonResponse([
                'message' => 'Internal server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/api/get/conversations', name: 'user_conversations', methods: ['GET'])]
    public function getUserConversations(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $conversations = $user->getConversations();

        $data = [];
        foreach ($conversations as $conversation) {
            $data[] = [
                'id' => $conversation->getId(),
                'title' => $conversation->getTitle(),
                'description' => $conversation->getDescription(),
                'createdBy' => $conversation->getCreatedBy()->getFirstName() . ' ' . $conversation->getCreatedBy()->getLastName(),
                'createdAt' => $conversation->getCreatedAt()?->format('Y-m-d H:i:s'), 
                'lastMessageAt' => $conversation->getLastMessageAt()?->format('Y-m-d H:i:s'),
            ];
        }
        
        return new JsonResponse($data);
    }
}
