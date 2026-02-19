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

    $this->logger->error('Test Papertrail', ['source' => 'openhub-backend']);

        $data = json_decode($request->getContent(), true);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        // Debug
        error_log("🔐 Login attempt for: " . $email);

        // Trouver l'utilisateur
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            error_log("❌ User not found: " . $email);
            return $this->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Vérifier le mot de passe
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            error_log("❌ Invalid password for: " . $email);
            return $this->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // 🔥 GÉNÉRER LE TOKEN JWT
        $token = $this->jwtManager->create($user);
        
        error_log("✅ Token generated for user: " . $user->getEmail());

        return $this->json([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
            ]
        ]);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse  // ✅ CORRECT
    {
        $data = json_decode($request->getContent(), true);
        
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $firstName = $data['firstName'] ?? '';
        $lastName = $data['lastName'] ?? '';
        $availabilityStart = $data['availabilityStart'] ?? null;
        $availabilityEnd = $data['availabilityEnd'] ?? null;
        $skills = $data['skills'] ?? null;

        // Validation des champs requis
        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            return $this->json([
                'status' => false,
                'message' => 'Email, password, first name and last name are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            return $this->json([
                'status' => false,
                'message' => 'This email is already in use'
            ], Response::HTTP_CONFLICT);
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);
        
        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Gérer les dates d'availability
        if ($availabilityStart) {
            try {
                $user->setAvailabilityStart(new \DateTimeImmutable($availabilityStart));
            } catch (\Exception $e) {
                return $this->json([
                    'status' => false,
                    'message' => 'Invalid availability start date format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($availabilityEnd) {
            try {
                $user->setAvailabilityEnd(new \DateTimeImmutable($availabilityEnd));
            } catch (\Exception $e) {
                return $this->json([
                    'status' => false,
                    'message' => 'Invalid availability end date format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Enregistrer l'utilisateur
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $this->json([
            'status' => true,
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d'),
                'skills' => $user->getSkills() ?? [],
            ]
        ], Response::HTTP_CREATED);
    }
}