<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\MessageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Conversation;
use App\Entity\Message;
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
public function getMessages(Security $security, MessageRepository $messageRepo): JsonResponse
{
    $user = $security->getUser();
    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }

    // Récupérer tous les messages (ou filtrer par user si nécessaire)
    $messages = $messageRepo->findAll(); // Ou ->findBy(['author' => $user])
    
    $data = [];
    foreach ($messages as $message) {
        $data[] = [
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'author' => $message->getAuthor()->getEmail(),
            'authorName' => $message->getAuthorName(),
            'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
            'conversationId' => $message->getConversation()->getId(),
            'conversationTitle' => $message->getConversationTitle(),
        ];
    }
    
    return new JsonResponse($data, 200);
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

#[Route('/api/create/message', name: 'create_message', methods: ['POST'])]
public function createMessage(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
{
    $user = $security->getUser();
    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);
    }

    $data = json_decode($request->getContent(), true);
    
    // Validate required fields
    if (empty($data['content'])) {
        return new JsonResponse(['message' => 'Content is required'], 400);
    }
    
    if (empty($data['conversation_id'])) {
        return new JsonResponse(['message' => 'Conversation ID is required'], 400);
    }

    // Fetch the Conversation entity
    $conversation = $em->getRepository(Conversation::class)->find($data['conversation_id']);
    
    if (!$conversation) {
        return new JsonResponse(['message' => 'Conversation not found'], 404);
    }

    $message = new Message();
    $message->setContent($data['content']);
    $message->setCreatedAt(new \DateTimeImmutable());
    $message->setConversation($conversation);
    $message->setAuthor($user); // ✅ Passe l'entité User, pas une string !

    $em->persist($message);
    $em->flush();

    return new JsonResponse([
        'id' => $message->getId(),
        'author' => $message->getAuthor()->getEmail(), // Récupère l'email depuis l'entité User
        'authorName' => $message->getAuthorName(), // Utilise ta méthode helper
        'content' => $message->getContent(),
        'createdAt' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
        'conversationId' => $conversation->getId(),
        'conversationTitle' => $conversation->getTitle()
    ], 201);
}

}
