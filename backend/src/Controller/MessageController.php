<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Conversation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;


class MessageController extends AbstractController
{
    private MessageRepository $messageRepository;

    public function __construct(MessageRepository $messageRepository)
    {
        $this->messageRepository = $messageRepository;

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
public function getUserConversations(EntityManagerInterface $em): JsonResponse
{
    $user = $this->getUser();

    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }

    // Récupère toutes les conversations créées par cet utilisateur
    $conversations = $em->getRepository(Conversation::class)
                        ->findBy(['createdBy' => $user], ['createdAt' => 'DESC']);

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


    #[Route('/api/get/messages', name: 'get_messages', methods: ['GET'])]
    public function getMessages(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $conversations = $user->getConversations();

        $messagesData = [];
        foreach ($conversations as $conversation) {
            foreach ($conversation->getMessages() as $message) {
                $messagesData[] = [
                    'id' => $message->getId(),
                    'content' => $message->getContent(),
                    'author' => $message->getAuthor()->getFirstName() . ' ' . $message->getAuthor()->getLastName(),
                    'createdAt' => $message->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'conversationId' => $conversation->getId(),
                    'conversationTitle' => $conversation->getTitle(),
                    'authorName' => $message->getAuthorName(),
                ];
            }
        }

        return new JsonResponse($messagesData);
    }

#[Route('/api/create/conversation', name: 'create_conversation', methods: ['POST'])]
public function createConversation(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
{
    $user = $security->getUser();
    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }

    $data = json_decode($request->getContent(), true);

    $conversation = new Conversation();
    $conversation->setTitle($data['title'] ?? 'Nouvelle Conversation');
    $conversation->setDescription($data['description'] ?? '');
    $conversation->setCreatedBy($user);
    $conversation->setCreatedAt(new \DateTimeImmutable());
    $conversation->setLastMessageAt(null); // pas encore de message

    $em->persist($conversation);
    $em->flush();

    return new JsonResponse([
        'id' => $conversation->getId(),
        'title' => $conversation->getTitle(),
        'description' => $conversation->getDescription(),
        'createdBy' => $user->getFirstName() . ' ' . $user->getLastName(),
        'createdAt' => $conversation->getCreatedAt()->format('Y-m-d H:i:s'),
        'lastMessageAt' => $conversation->getLastMessageAt()
    ]);
}

}
