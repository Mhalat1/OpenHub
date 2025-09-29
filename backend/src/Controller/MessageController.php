<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

use App\Repository\UserRepository;
use App\Repository\MessageRepository;
use App\Entity\User;
use App\Entity\Message;

final class MessageController extends AbstractController
{
    private UserRepository $userRepository;
    private MessageRepository $messageRepository;

    public function __construct(
        UserRepository $userRepository,
        MessageRepository $messageRepository
    ) {
        $this->userRepository = $userRepository;
        $this->messageRepository = $messageRepository;
    }

    #[Route('/api/getMessage', name: 'get_message', methods: ['GET'])]
    public function getMessage(Security $security): JsonResponse
    {
        $user = $security->getUser();
        
        if (!$user) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }
        
        if (!$user instanceof \App\Entity\User) {
            $userEntity = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
            if (!$userEntity) {
                return new JsonResponse(['message' => 'User entity not found'], 404);
            }
            $user = $userEntity;
        }

        // Récupérer tous les messages où l'utilisateur est destinataire OU expéditeur
        $messages = $this->messageRepository->findBy([
            'recipient' => $user->getId()
        ]);

        // Ou pour récupérer tous les messages (envoyés ET reçus) :
        // $sentMessages = $this->messageRepository->findBy(['sender' => $user->getId()]);
        // $receivedMessages = $this->messageRepository->findBy(['recipient' => $user->getId()]);
        // $messages = array_merge($sentMessages, $receivedMessages);

        // Formater les messages pour le JSON
        $messagesData = [];
        foreach ($messages as $message) {
            $messagesData[] = [
                'id' => $message->getId(),
                'title' => $message->getTitle(),
                'content' => $message->getContent(),
                'sender' => $message->getSender(),
                'recipient' => $message->getRecipient(),
                'sent_at' => $message->getSentAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($messagesData);
    }

    // Route alternative pour récupérer TOUS les messages (envoyés + reçus)
    #[Route('/api/getAllMessages', name: 'get_all_messages', methods: ['GET'])]
    public function getAllMessages(Security $security): JsonResponse
    {
        $user = $security->getUser();
        
        if (!$user) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }
        
        if (!$user instanceof \App\Entity\User) {
            $userEntity = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
            if (!$userEntity) {
                return new JsonResponse(['message' => 'User entity not found'], 404);
            }
            $user = $userEntity;
        }

        $userId = $user->getId();

        // Récupérer tous les messages envoyés ET reçus
        $sentMessages = $this->messageRepository->findBy(['sender' => $userId]);
        $receivedMessages = $this->messageRepository->findBy(['recipient' => $userId]);
        
        $allMessages = array_merge($sentMessages, $receivedMessages);

        // Trier par date (plus récent en premier)
        usort($allMessages, function($a, $b) {
            return $b->getSentAt() <=> $a->getSentAt();
        });

        $messagesData = [];
        foreach ($allMessages as $message) {
            $messagesData[] = [
                'id' => $message->getId(),
                'title' => $message->getTitle(),
                'content' => $message->getContent(),
                'sender' => $message->getSender(),
                'recipient' => $message->getRecipient(),
                'sent_at' => $message->getSentAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($messagesData);
    }
}