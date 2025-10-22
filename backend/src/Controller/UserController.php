<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Entity\Skills;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

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
        ]);
    }

    
    #[Route('/api/user/skills', name: 'api_user_skills', methods: ['GET'])]
    public function getUserSkills(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            // Récupération directe des compétences via la relation ManyToMany
            $skills = $user->getSkills();

            // Conversion en tableau simple
            $data = [];
            foreach ($skills as $skill) {
                $data[] = [
                    'id' => $skill->getId(),
                    'name' => $skill->getName(),
                    'description' => $skill->getDescription(),
                    'duree' => $skill->getDuree() ? $skill->getDuree()->format('Y-m-d') : null,
                    'technoUtilisees' => $skill->getTechnoUtilisees(),
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error fetching user skills',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/skills', name: 'api_skills', methods: ['GET'])]
    public function getAllSkills(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $skillsRepo = $this->manager->getRepository(Skills::class);
            $Allskills = $skillsRepo->findAll();

            error_log("Found " . count($Allskills) . " skills");

            $data = [];
            foreach ($Allskills as $skill) {
                try {
                    $skillData = [
                        'id' => $skill->getId(),
                        'name' => $skill->getName() ?? 'N/A',
                        'description' => $skill->getDescription() ?? 'N/A',
                        'duree' => null,
                        'technoUtilisees' => $skill->getTechnoUtilisees() ?? 'N/A',
                    ];

                    if (method_exists($skill, 'getDuree')) {
                        $duree = $skill->getDuree();
                        if ($duree instanceof \DateTimeInterface) {
                            $skillData['duree'] = $duree->format('Y-m-d');
                        }
                    }

                    $data[] = $skillData;
                } catch (\Exception $e) {
                    error_log("Error processing skill " . $skill->getId() . ": " . $e->getMessage());
                    continue;
                }
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            error_log("Error in getAllSkills: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return new JsonResponse([
                'error' => 'Error fetching all skills',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ], 500);
        }
    }

    #[Route('/api/user/add/skills', name: 'api_user_skills_add', methods: ['POST'])]
    public function addUserSkill(
        Request $request,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        // 1. Vérifier que l'utilisateur est connecté
        $user = $security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Utilisateur non connecté'], 401);
        }

        try {
            // 2. Récupérer les données envoyées
            $data = json_decode($request->getContent(), true);
            $skillId = $data['skill_id'] ?? null;

            // 3. Vérifier qu'un skill_id est bien envoyé
            if (!$skillId) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'ID de compétence manquant'
                ], 400);
            }

            // 4. Chercher la compétence dans la base
            $skill = $em->getRepository(Skills::class)->find($skillId);
            if (!$skill) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Compétence non trouvée'
                ], 404);
            }

            // 5. Vérifier si l'utilisateur a déjà cette compétence
            if ($user->getSkills()->contains($skill)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Vous avez déjà cette compétence'
                ], 400);
            }

            // 6. AJOUTER LA COMPÉTENCE À LA COLLECTION
            $user->addSkill($skill);

            // 7. Sauvegarder dans la base
            $em->persist($user);
            $em->flush();

            // 8. Réponse de succès
            return new JsonResponse([
                'success' => true,
                'message' => 'Compétence ajoutée avec succès',
                'skill_name' => $skill->getName()
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/user/availability', name: 'api_user_availability_change', methods: ['POST'])]
    public function changeAvailability(
        Request $request,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        // 1. Vérifier que l'utilisateur est connecté
        $user = $security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'Utilisateur non connecté'], 401);
        }

        try {
            // 2. Récupérer les données envoyées
            $data = json_decode($request->getContent(), true);
            $availabilityStart = $data['availabilityStart'] ?? null;
            $availabilityEnd = $data['availabilityEnd'] ?? null;

            // 3. Vérifier qu'un availability est bien envoyé
            if (!$availabilityStart || !$availabilityEnd) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'availability not sended'
                ], 400);
            }

            // 4. Convertir les chaînes en DateTimeImmutable
            try {
                $startDate = new \DateTimeImmutable($availabilityStart);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid availabilityStart date format'
                ], 400);
            }

            try {
                $endDate = new \DateTimeImmutable($availabilityEnd);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid availabilityEnd date format'
                ], 400);
            }

            // 5. Mettre à jour l'utilisateur
            $user->setAvailabilityStart($startDate);
            $user->setAvailabilityEnd($endDate);

            // 6. Sauvegarder dans la base
            $em->persist($user);
            $em->flush();

            // 7. Réponse de succès
            return new JsonResponse([
                'success' => true,
                'message' => 'Availability updated successfully',
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d')
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/user/projects', name: 'api_user_projects', methods: ['GET'])]
    public function getUserProjects(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $projects = $user->getProject();

            $data = [];
            foreach ($projects as $project) {
                $data[] = [
                    'id' => $project->getId(),
                    'name' => $project->getName(),
                    'description' => $project->getDescription(),
                    'requiredSkills' => $project->getRequiredSkills(),
                    'startDate' => $project->getStartDate() ? $project->getStartDate()->format('Y-m-d') : null,
                    'endDate' => $project->getEndDate() ? $project->getEndDate()->format('Y-m-d') : null,
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error fetching user projects',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/user/add/project', name: 'api_user_add_project', methods: ['POST'])]
    public function addUserToProject(
        Request $request,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $security->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $projectId = $data['project_id'] ?? null;

            if (!$projectId) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Project ID required'
                ], 400);
            }

            $project = $em->getRepository(Project::class)->find($projectId);
            
            if (!$project) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Project not found'
                ], 404);
            }

            // Vérifier si l'utilisateur est déjà dans ce projet
            if ($user->getProject()->contains($project)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Already in this project'
                ], 400);
            }

            $user->addProject($project);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'project_name' => $project->getName(),
                'message' => 'Added to project successfully'
            ], 200);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }



    #[Route('/api/invitations/pending', name: 'api_pending_invitations', methods: ['GET'])]
    public function getInvitations(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        $Invitations = $user->getInvitations();

        $invitations = [];
        foreach ($Invitations as $inviter) {
            $invitations[] = [
                'id' => $inviter->getId(),
                'firstName' => $inviter->getFirstName(),
                'lastName' => $inviter->getLastName(),
                'email' => $inviter->getEmail()
            ];
        }

        return new JsonResponse([
            'success' => true,
            'invitations' => $invitations
        ]);
    }

    #[Route('/api/invitations/accept', name: 'api_accept_invitations', methods: ['POST'])]
    public function acceptInvitations(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $inviterId = $data['inviter_id'] ?? null;

        if (!$inviterId) {
            return new JsonResponse(['success' => false, 'message' => 'ID invitant requis'], 400);
        }

        $inviter = $em->getRepository(User::class)->find($inviterId);
        
        if (!$inviter) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        // Vérifier si l'invitations existe
        if (!$user->getInvitations()->contains($inviter)) {
            return new JsonResponse(['success' => false, 'message' => 'Invitations non trouvée'], 404);
        }

        // Retirer des invitations
        $user->removeInvitations($inviter);
        $inviter->removeInvitations($user);

        // Ajouter aux amis
        $user->addFriend($inviter);
        $inviter->addFriend($user);

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Invitations acceptée avec succès',
            'friend' => [
                'id' => $inviter->getId(),
                'firstName' => $inviter->getFirstName(),
                'lastName' => $inviter->getLastName()
            ]
        ]);
    }

    #[Route('/api/invitations/reject', name: 'api_reject_invitations', methods: ['POST'])]
    public function rejectInvitations(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Non authentifié'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $inviterId = $data['inviter_id'] ?? null;

        if (!$inviterId) {
            return new JsonResponse(['success' => false, 'message' => 'ID invitant requis'], 400);
        }

        $inviter = $em->getRepository(User::class)->find($inviterId);
        
        if (!$inviter) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non trouvé'], 404);
        }

        // Vérifier si l'invitations existe
        if (!$user->getInvitations()->contains($inviter)) {
            return new JsonResponse(['success' => false, 'message' => 'Invitations non trouvée'], 404);
        }

        // Retirer des invitations
        $user->removeInvitations($inviter);
        $inviter->removeInvitations($user);

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Invitations refusée'
        ]);
    }

    #[Route('/api/user/friends', name: 'api_user_friends', methods: ['GET'])]
    public function getUserFriends(Security $security): JsonResponse
    {
        $user = $security->getUser();  
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $friends = $user->getFriends();

            $data = [];
            foreach ($friends as $friend) {
                $data[] = [
                    'id' => $friend->getId(),
                    'firstName' => $friend->getFirstName(),
                    'lastName' => $friend->getLastName(),
                    'email' => $friend->getEmail()
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error fetching user friends',
                'message' => $e->getMessage()
            ], 500);
        }
    }






}