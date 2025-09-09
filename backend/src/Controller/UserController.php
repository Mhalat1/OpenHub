<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class UserController extends AbstractController
{
    private EntityManagerInterface $manager;
    private $userRepository;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager = $manager;
        $this->userRepository = $this->manager->getRepository(User::class);
    }

    #[Route('/api/userCreate', name: 'user_create', methods: ['POST'])]
    public function userCreate(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $firstName = $data['firstName'] ?? null;
        $lastName = $data['lastName'] ?? null;
        $availabilityStart = $data['availabilityStart'] ?? null;
        $availabilityEnd = $data['availabilityEnd'] ?? null;
        $skills = $data['skills'] ?? null;

        if (!$email || !$password) {
            return new JsonResponse([
                'status' => false,
                'message' => 'Email and password are required'
            ], 400);
        }

        $emailExists = $this->userRepository->findOneBy(['email' => $email]);

        if ($emailExists) {
            return new JsonResponse([
                'status' => false,
                'message' => 'This email is already in use'
            ], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);
        $user->setAvailabilityStart($availabilityStart ? new \DateTimeImmutable($availabilityStart) : null);
        $user->setAvailabilityEnd($availabilityEnd ? new \DateTimeImmutable($availabilityEnd) : null);
        $user->setSkills($skills);

        $this->manager->persist($user);
        $this->manager->flush();

        return new JsonResponse([
            'status' => true,
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d'),
                'skills' => $user->getSkills(),
            ]
        ], 201);
    }

    #[Route('/api/getAllUsers', name: 'get_all_users', methods: ['GET'])]
    public function getAllUsers(): JsonResponse
    {
        $users = $this->userRepository->findAll();

        $result = array_map(fn(User $user) => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
            'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d'),
            'skills' => $user->getSkills(),
        ], $users);

        return new JsonResponse($result);
    }

    #[Route('/api/getConnectedUser', name: 'get_connected_user', methods: ['GET'])]
    public function getConnectedUser(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        // Ensure $user is an instance of App\Entity\User
        if (!$user instanceof User) {
            // Try to fetch the User entity by email if $user is a UserInterface (e.g., Symfony's default user)
            $userEntity = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
            if (!$userEntity) {
                return new JsonResponse(['message' => 'User entity not found'], 404);
            }
            $user = $userEntity;
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
            'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d'),
            'skills' => $user->getSkills(),
        ]);
    }
}
