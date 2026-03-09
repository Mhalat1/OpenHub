<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\PapertrailService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private PapertrailService $papertrailLogger,
    ) {}

    #[Route('/api/login_check', name: 'api_login_check', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email    = $data['email']    ?? '';
        $password = $data['password'] ?? '';

        $this->papertrailLogger->info('Login attempt', [
            'email' => $email,
        ]);

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $this->papertrailLogger->warning('User not found during login', [
                'email' => $email,
            ]);
            return $this->json([
                'message' => 'Invalid credentials',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $this->papertrailLogger->info('TEST LOG FROM AUTH - please appear');
        $this->papertrailLogger->error('Invalid password attempt - ERROR LEVEL', [
            'user_id' => $user->getId(),
            'email'   => $email,
        ]);

        $token = $this->jwtManager->create($user);

        $this->papertrailLogger->info('Token generated - Login successful', [
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

        $this->papertrailLogger->info('Registration attempt', [
            'email' => $email,
        ]);

        // Validation du format des noms
        if (!preg_match('/^[\p{L}\s\'\-]+$/uD', $firstName) || mb_strlen($firstName) < 2 || mb_strlen($firstName) > 100) {
            $this->papertrailLogger->warning('Invalid firstName format on registration', [
                'email'      => $email,
                'first_name' => $firstName,
            ]);
            return $this->json([
                'status'  => false,
                'message' => 'Invalid first name format'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!preg_match('/^[\p{L}\s\'\-]+$/uD', $lastName) || mb_strlen($lastName) < 2 || mb_strlen($lastName) > 100) {
            $this->papertrailLogger->warning('Invalid lastName format on registration', [
                'email'     => $email,
                'last_name' => $lastName,
            ]);
            return $this->json([
                'status'  => false,
                'message' => 'Invalid last name format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $availabilityStart = $data['availabilityStart'] ?? null;
        $availabilityEnd   = $data['availabilityEnd']   ?? null;

        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            $this->papertrailLogger->warning('Missing required fields on registration', [
                'email'          => $email,
                'has_password'   => !empty($password),
                'has_first_name' => !empty($firstName),
                'has_last_name'  => !empty($lastName),
            ]);
            return $this->json([
                'status'  => false,
                'message' => 'Email, password, first name and last name are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation stricte du format email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->papertrailLogger->warning('Invalid email format on registration', [
                'email' => $email,
            ]);
            return $this->json([
                'status'  => false,
                'message' => 'Invalid email format. Use format: name@domain.xxx (ex: jean@example.com)'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation supplémentaire du domaine.
        // Note : le bloc !str_contains est du code défensif redondant — FILTER_VALIDATE_EMAIL
        // de PHP 8.x exige déjà un point dans le domaine, donc cette branche est
        // inatteignable en pratique. Elle est conservée pour la sécurité en profondeur
        // mais exclue de la couverture de code pour cette raison.
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            $domain = $parts[1];
            // @codeCoverageIgnoreStart
            if (!str_contains($domain, '.')) {
                $this->papertrailLogger->warning('Email domain missing dot on registration', [
                    'email'  => $email,
                    'domain' => $domain,
                ]);
                return $this->json([
                    'status'  => false,
                    'message' => 'Invalid email format. Domain must contain a dot (ex: gmail.com)'
                ], Response::HTTP_BAD_REQUEST);
            }
            // @codeCoverageIgnoreEnd

            $tldParts = explode('.', $domain);
            $tld = end($tldParts);
            if (strlen($tld) < 2) {
                $this->papertrailLogger->warning('Email TLD too short on registration', [
                    'email' => $email,
                    'tld'   => $tld,
                ]);
                return $this->json([
                    'status'  => false,
                    'message' => 'Invalid email format. Extension must be at least 2 characters (ex: .com, .fr)'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $this->papertrailLogger->warning('Registration attempt with already existing email', [
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
                $this->papertrailLogger->warning('Invalid availability start date on registration', [
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
                $this->papertrailLogger->warning('Invalid availability end date on registration', [
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

        $this->papertrailLogger->info('✅ User registered successfully', [
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