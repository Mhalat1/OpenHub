<?php

namespace App\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/login_check', name: 'api_login_check', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email    = $data['email']    ?? '';
        $password = $data['password'] ?? '';

        $this->logger->info('Login attempt', [
            'email' => $email,
        ]);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->logger->error('Test Papertrail ERROR', ['source' => 'openhub-backend']);

        if (!$user) {
            $this->logger->warning('User not found', [
                'email' => $email,
            ]);
            return $this->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->logger->warning('Invalid password', [
                'email' => $email,
            ]);
            return $this->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtManager->create($user);

        $this->logger->info('Token generated', [
            'user_id' => $user->getId(),
            'email'   => $user->getEmail(),
        ]);

        return $this->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->getId(),
                'email'     => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName'  => $user->getLastName(),
            ]
        ]);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data      = json_decode($request->getContent(), true);
        $email     = $data['email']             ?? '';
        $password  = $data['password']          ?? '';
        $firstName = $data['firstName']         ?? '';
        $lastName  = $data['lastName']          ?? '';
        // Validation du format des noms
        if (!preg_match('/^[\p{L}\s\'\-]+$/uD', $firstName) || mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100) {
            return $this->json([
                'status'  => false,
                'message' => 'Invalid first name format'
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!preg_match('/^[\p{L}\s\'\-]+$/uD', $lastName) || mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100) {
            return $this->json([
                'status'  => false,
                'message' => 'Invalid last name format'
            ], Response::HTTP_BAD_REQUEST);
        }
        $availabilityStart = $data['availabilityStart'] ?? null;
        $availabilityEnd   = $data['availabilityEnd']   ?? null;

        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            return $this->json([
                'status'  => false,
                'message' => 'Email, password, first name and last name are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->logger->warning('Registration attempt with existing email', [
                'email' => $email,
            ]);
            return $this->json([
                'status'  => false,
                'message' => 'This email is already in use'
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        if ($availabilityStart) {
            try {
                $user->setAvailabilityStart(new \DateTimeImmutable($availabilityStart));
            } catch (\Exception $e) {
                $this->logger->warning('Invalid availability start date', [
                    'email' => $email,
                    'value' => $availabilityStart,
                    'error' => $e->getMessage(),
                ]);
                return $this->json([
                    'status'  => false,
                    'message' => 'Invalid availability start date format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($availabilityEnd) {
            try {
                $user->setAvailabilityEnd(new \DateTimeImmutable($availabilityEnd));
            } catch (\Exception $e) {
                $this->logger->warning('Invalid availability end date', [
                    'email' => $email,
                    'value' => $availabilityEnd,
                    'error' => $e->getMessage(),
                ]);
                return $this->json([
                    'status'  => false,
                    'message' => 'Invalid availability end date format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->logger->info('User registered', [
            'user_id' => $user->getId(),
            'email'   => $user->getEmail(),
        ]);

        return $this->json([
            'status'  => true,
            'message' => 'User created successfully',
            'user'    => [
                'id'                => $user->getId(),
                'email'             => $user->getEmail(),
                'firstName'         => $user->getFirstName(),
                'lastName'          => $user->getLastName(),
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd'   => $user->getAvailabilityEnd()?->format('Y-m-d'),
                'skills'            => $user->getSkills() ?? [],
            ]
        ], Response::HTTP_CREATED);
    }
}