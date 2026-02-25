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
        try {
            $data = json_decode($request->getContent(), true);

            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;
            $firstName = $data['firstName'] ?? null;
            $lastName = $data['lastName'] ?? null;
            $availabilityStart = $data['availabilityStart'] ?? null;
            $availabilityEnd = $data['availabilityEnd'] ?? null;
            $skills = $data['skills'] ?? null;  // ← "PHP,React" ou ["PHP", "React"]

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

            // Validation des dates (identique)
            if ($availabilityStart || $availabilityEnd) {
                $today = new \DateTimeImmutable();
                if ($availabilityStart) {
                    $startDate = new \DateTimeImmutable($availabilityStart);
                    if ($startDate < $today) {
                        return new JsonResponse(['status' => false, 'message' => 'La date de début doit être dans le futur'], 400);
                    }
                }
                if ($availabilityEnd) {
                    $endDate = new \DateTimeImmutable($availabilityEnd);
                    if ($endDate < $today) {
                        return new JsonResponse(['status' => false, 'message' => 'La date de fin doit être dans le futur'], 400);
                    }
                }
                if ($availabilityStart && $availabilityEnd && $endDate < $startDate) {
                    return new JsonResponse(['status' => false, 'message' => 'La date de fin doit être après la date de début'], 400);
                }
            }

            // Création de l'utilisateur
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setRoles(['ROLE_USER']);
            $user->setAvailabilityStart($availabilityStart ? new \DateTimeImmutable($availabilityStart) : null);
            $user->setAvailabilityEnd($availabilityEnd ? new \DateTimeImmutable($availabilityEnd) : null);

            // 🔥 TRAITEMENT DES COMPÉTENCES 🔥
            if ($skills) {
                $skillsRepo = $this->manager->getRepository(Skills::class);

                // Si c'est une chaîne "PHP,React"
                if (is_string($skills)) {
                    $skillNames = array_map('trim', explode(',', $skills));
                }
                // Si c'est déjà un tableau ["PHP", "React"]
                elseif (is_array($skills)) {
                    $skillNames = $skills;
                } else {
                    $skillNames = [];
                }

                foreach ($skillNames as $skillName) {
                    if (empty($skillName)) continue;

                    // Chercher si la compétence existe déjà
                    $skill = $skillsRepo->findOneBy(['name' => $skillName]);

                    // Si elle n'existe pas, la créer
                    if (!$skill) {
                        $skill = new Skills();
                        $skill->setName($skillName);
                        $skill->setDescription($skillName); // ou une description par défaut
                        $skill->setTechnoUtilisees($skillName);
                        $skill->setDuree(new \DateTimeImmutable('+1 year')); // durée par défaut
                        $this->manager->persist($skill);
                    }

                    // Ajouter la compétence à l'utilisateur
                    $user->addSkill($skill);
                }
            }

            // Sauvegarde
            $this->manager->persist($user);
            $this->manager->flush();

            // Récupérer les noms des compétences pour la réponse
            $skillNames = [];
            foreach ($user->getSkills() as $skill) {
                $skillNames[] = $skill->getName();
            }

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
                    'skills' => $skillNames,  // ← Maintenant inclus !
                ]
            ], 201);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'file'  => basename($e->getFile()),
                'line'  => $e->getLine(),
            ], 500);
        }
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



    #[Route('/api/user/delete/skill', name: 'api_user_skill_delete', methods: ['DELETE'])]
    public function removeUserSkill(
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

            // 5. Vérifier si l'utilisateur possède bien cette compétence
            if (!$user->getSkills()->contains($skill)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Vous ne possédez pas cette compétence'
                ], 400);
            }

            // 6. RETIRER LA COMPÉTENCE DE LA COLLECTION
            $user->removeSkill($skill);

            // 7. Sauvegarder dans la base
            $em->flush();

            // 8. Réponse de succès
            return new JsonResponse([
                'success' => true,
                'message' => 'Compétence retirée avec succès',
                'skill_name' => $skill->getName()
            ], 200);
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


    #[Route('/api/user/delete/project', name: 'api_user_delete_project', methods: ['DELETE'])]
    public function removeUserFromProject(
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

            // Vérifier si l'utilisateur est bien dans ce projet
            if (!$user->getProject()->contains($project)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User is not in this project'
                ], 400);
            }

            $user->removeUserProject($project);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'project_name' => $project->getName(),
                'message' => 'Removed from project successfully'
            ], 200);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/invitations/received', name: 'app_received_invitations', methods: ['GET'])]
    public function getReceivedInvitations(Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $received = [];
        foreach ($user->getReceivedInvitations() as $sender) {
            // Filtrer ceux qui sont déjà amis
            if (!$user->getFriends()->contains($sender)) {
                $received[] = [
                    'id' => $sender->getId(),
                    'firstName' => $sender->getFirstName(),
                    'lastName' => $sender->getLastName(),
                    'email' => $sender->getEmail(),
                ];
            }
        }

        return new JsonResponse($received);
    }

    #[Route('/api/invitations/sent', name: 'app_sent_invitations', methods: ['GET'])]
    public function getSentInvitations(Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $data = [];
        foreach ($user->getSentInvitations() as $invitedUser) {
            if (!$user->getFriends()->contains($invitedUser)) {
                $data[] = [
                    'id' => $invitedUser->getId(),
                    'firstName' => $invitedUser->getFirstName(),
                    'lastName' => $invitedUser->getLastName(),
                    'email' => $invitedUser->getEmail(),
                ];
            }
        }

        return new JsonResponse($data);
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
            if ($user->getSentInvitations()->contains($friend)) {
                return new JsonResponse(['success' => false, 'message' => 'Invitation déjà envoyée'], 400);
            }

            // Ajouter l'invitation correctement : user -> friend
            $user->addSentInvitation($friend);  // user_source = $user, user_target = $friend
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


    #[Route('/api/invitations/accept/{senderId}', name: 'api_accept_invitation', methods: ['POST'])]
    public function acceptInvitation(
        int $senderId,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $sender = $em->getRepository(User::class)->find($senderId);
        if (!$sender) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur expéditeur non trouvé'], 404);
        }

        // Vérifie si une invitation a bien été reçue de cet utilisateur
        if (!$user->getReceivedInvitations()->contains($sender)) {
            return new JsonResponse(['success' => false, 'message' => 'Aucune invitation trouvée.'], 404);
        }

        try {
            // Retirer l’invitation dans les deux sens
            $user->getReceivedInvitations()->removeElement($sender);
            $sender->getSentInvitations()->removeElement($user);

            // Ajouter en amis dans les deux sens
            $user->addFriend($sender);
            $sender->addFriend($user);

            $em->persist($user);
            $em->persist($sender);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Invitation acceptée. Vous êtes maintenant amis.',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage(),
            ], 500);
        }
    }



    #[Route('/api/invitations/delete-received/{senderId}', name: 'api_delete_received_invitation', methods: ['DELETE'])]
    public function deleteReceivedInvitation(
        int $senderId,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $sender = $em->getRepository(User::class)->find($senderId);
        if (!$sender) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur expéditeur non trouvé'], 404);
        }

        // Vérifie si le user connecté a reçu une invitation de $sender
        if (!$user->getReceivedInvitations()->contains($sender)) {
            return new JsonResponse(['success' => false, 'message' => 'Invitation non trouvée'], 404);
        }

        try {
            // Supprimer l'invitation dans les deux sens
            $user->getReceivedInvitations()->removeElement($sender);
            $sender->getSentInvitations()->removeElement($user);

            $em->persist($user);
            $em->persist($sender);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Invitation reçue supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/invitations/delete-sent/{receiverId}', name: 'api_delete_sent_invitation', methods: ['DELETE'])]
    public function deleteSentInvitation(
        int $receiverId,
        Security $security,
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $receiver = $em->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur destinataire non trouvé'], 404);
        }

        if (!$user->getSentInvitations()->contains($receiver)) {
            return new JsonResponse(['success' => false, 'message' => 'Invitation non trouvée'], 404);
        }

        try {
            $user->getSentInvitations()->removeElement($receiver);
            $receiver->getReceivedInvitations()->removeElement($user);

            $em->persist($user);
            $em->persist($receiver);
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Invitation envoyée supprimée avec succès'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
}
