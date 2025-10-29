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
use App\Service\UserService;

final class UserController extends AbstractController
{
    private EntityManagerInterface $manager;
    private $userRepository;
    private UserService $userService;

    public function __construct(EntityManagerInterface $manager, UserService $userService)
    {
        $this->manager = $manager;
        $this->userRepository = $this->manager->getRepository(User::class);
        $this->userService = $userService;
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
        $userData = $this->userService->findAll($user);


        return new JsonResponse([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
            'availabilityEnd' => $user->getAvailabilityEnd()?->format('Y-m-d'),
            'userData' => $userData,
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

    #[Route('/api/skills/create', name: 'api_create_skill', methods: ['POST'])]
    public function createSkill(Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $data = json_decode($request->getContent(), true);

            // Validation des données
            if (empty($data['name'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Skill name is required'
                ], 400);
            }

            if (empty($data['description'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Description is required'
                ], 400);
            }

            if (empty($data['technoUtilisees'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Technologies used is required'
                ], 400);
            }

            if (empty($data['duree'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Duration is required'
                ], 400);
            }

            // Vérifier si la compétence existe déjà
            $skillsRepo = $this->manager->getRepository(Skills::class);
            $existingSkill = $skillsRepo->findOneBy(['Name' => $data['name']]);

            if ($existingSkill) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'A skill with this name already exists'
                ], 409);
            }

            // Créer la nouvelle compétence
            $skill = new Skills();
            $skill->setName($data['name']);
            $skill->setDescription($data['description']);
            $skill->setTechnoUtilisees($data['technoUtilisees']);
            
            // Convertir la date en DateTimeImmutable
            try {
                $duree = new \DateTimeImmutable($data['duree']);
                $skill->setDuree($duree);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid date format. Use YYYY-MM-DD'
                ], 400);
            }

            // Persister en base de données
            $this->manager->persist($skill);
            $this->manager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Skill created successfully',
                'skill' => [
                    'id' => $skill->getId(),
                    'name' => $skill->getName(),
                    'description' => $skill->getDescription(),
                    'technoUtilisees' => $skill->getTechnoUtilisees(),
                    'duree' => $skill->getDuree()->format('Y-m-d')
                ]
            ], 201);

        } catch (\Exception $e) {
            error_log("Error creating skill: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Error creating skill',
                'message' => $e->getMessage()
            ], 500);
        }
    }


     #[Route('/api/skills/update/{id}', name: 'api_update_skill', methods: ['PUT', 'PATCH'])]
    public function updateSkill(int $id, Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $skillsRepo = $this->manager->getRepository(Skills::class);
            $skill = $skillsRepo->find($id);

            if (!$skill) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Skill not found'
                ], 404);
            }

            $data = json_decode($request->getContent(), true);

            // Mise à jour des champs
            if (isset($data['name'])) {
                // Vérifier l'unicité du nouveau nom
                $existingSkill = $skillsRepo->findOneBy(['Name' => $data['name']]);
                if ($existingSkill && $existingSkill->getId() !== $id) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'A skill with this name already exists'
                    ], 409);
                }
                $skill->setName($data['name']);
            }

            if (isset($data['description'])) {
                $skill->setDescription($data['description']);
            }

            if (isset($data['technoUtilisees'])) {
                $skill->setTechnoUtilisees($data['technoUtilisees']);
            }

            if (isset($data['duree'])) {
                try {
                    $duree = new \DateTimeImmutable($data['duree']);
                    $skill->setDuree($duree);
                } catch (\Exception $e) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Invalid date format. Use YYYY-MM-DD'
                    ], 400);
                }
            }

            $this->manager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Skill updated successfully',
                'skill' => [
                    'id' => $skill->getId(),
                    'name' => $skill->getName(),
                    'description' => $skill->getDescription(),
                    'technoUtilisees' => $skill->getTechnoUtilisees(),
                    'duree' => $skill->getDuree()->format('Y-m-d')
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Error updating skill: " . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Error updating skill',
                'message' => $e->getMessage()
            ], 500);
        }
    }


     #[Route('/api/skills/delete/{id}', name: 'api_delete_skill', methods: ['DELETE'])]
    public function deleteSkill(int $id, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $skillsRepo = $this->manager->getRepository(Skills::class);
            $skill = $skillsRepo->find($id);

            if (!$skill) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Skill not found'
                ], 404);
            }

            $skillName = $skill->getName();

            $this->manager->remove($skill);
            $this->manager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "Skill '$skillName' deleted successfully"
            ]);

        } catch (\Exception $e) {
            error_log("Error deleting skill: " . $e->getMessage());
            
            return new JsonResponse([
                'success' => false,
                'error' => 'Error deleting skill',
                'message' => $e->getMessage()
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
public function pendingInvitations(EntityManagerInterface $em, Security $security): JsonResponse
{
    $user = $security->getUser();
    if (!$user instanceof User) {
        return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
    }

    $conn = $em->getConnection();
    $sql = "SELECT u.id, u.first_name, u.last_name
            FROM user_invitations ui
            INNER JOIN user u ON ui.user_source = u.id
            WHERE ui.user_target = :userId";
    
    // executeQuery() renvoie un Result qui a fetchAllAssociative()
    $result = $conn->executeQuery($sql, ['userId' => $user->getId()]);
    $invitations = $result->fetchAllAssociative();

    // Filtrer les invitations qui sont déjà amis
    $validInvitations = array_filter($invitations, function($inv) use ($user) {
        foreach ($user->getFriends() as $friend) {
            if ($friend->getId() === $inv['id']) {
                return false; // déjà ami, on ignore
            }
        }
        return true; // invitation valide
    });

    return new JsonResponse(['invitations' => array_values($validInvitations)], 200);
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




    #[Route('/api/delete/friends/{id}', name: 'api_delete_friend', methods: ['DELETE'])]
public function deleteFriend(int $id, Security $security, EntityManagerInterface $em): JsonResponse
{
    $user = $security->getUser();

    if (!$user instanceof User) {
        return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
    }

    $friend = $em->getRepository(User::class)->find($id);

    if (!$friend) {
        return new JsonResponse(['success' => false, 'message' => 'Ami introuvable'], 404);
    }

    // Vérifie si c’est bien un ami
    if (!$user->getFriends()->contains($friend)) {
        return new JsonResponse(['success' => false, 'message' => 'Cet utilisateur n’est pas dans votre liste d’amis'], 400);
    }

    // Supprime la relation d’amitié dans les deux sens
    $user->removeFriend($friend);
    $friend->removeFriend($user);

    $em->flush();

    return new JsonResponse([
        'success' => true,
        'message' => 'Ami supprimé avec succès'
    ]);
}



#[Route('api/send/invitation', name: 'api_send_invitation', methods: ['POST'])]
public function sendInvitation(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
{
    $user = $security->getUser();  
    if (!$user instanceof User) {
        return new JsonResponse(['message' => 'User not authenticated'], 401);  
    }

    try {
        $data = json_decode($request->getContent(), true);
        $friendId = $data['friend_id'] ?? null;

        if (!$friendId) {
            return new JsonResponse(['success' => false, 'message' => 'Friend ID required'], 400);
        }

        $friend = $em->getRepository(User::class)->find($friendId);
        if (!$friend) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        // Vérifier si déjà amis
        if ($user->getFriends()->contains($friend)) {
            return new JsonResponse(['success' => false, 'message' => 'Vous êtes déjà amis'], 400);
        }

        // Vérifier si invitation déjà envoyée
        if ($user->getInvitations()->contains($friend)) {
            return new JsonResponse(['success' => false, 'message' => 'Invitation déjà envoyée'], 400);
        }

        // Ajouter l'invitation correctement : user -> friend
        $user->addInvitations($friend);  // user_source = $user, user_target = $friend
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Invitation envoyée avec succès'
        ], 200);

    } catch (\Exception $e) {
        return new JsonResponse([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}








}