<?php

namespace App\Controller;

use App\Service\AxiomService;
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

    public function __construct(
        EntityManagerInterface $manager,
        UserService $userService,
        private AxiomService $AxiomLogger,
    ) {
        $this->manager        = $manager;
        $this->userRepository = $this->manager->getRepository(User::class);
        $this->userService    = $userService;
    }

    #[Route('/api/userCreate', name: 'user_create', methods: ['POST'])]
    public function userCreate(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email             = $data['email']             ?? null;
        $password          = $data['password']          ?? null;
        $firstName         = $data['firstName']         ?? null;
        $lastName          = $data['lastName']          ?? null;
        $availabilityStart = $data['availabilityStart'] ?? null;
        $availabilityEnd   = $data['availabilityEnd']   ?? null;

        $this->AxiomLogger->info('User creation attempt', [
            'email' => $email,
        ]);

        if (!$email || !$password) {
            $this->AxiomLogger->warning('User creation - missing email or password', [
                'email' => $email,
            ]);
            return new JsonResponse([
                'status'  => false,
                'message' => 'Email and password are required'
            ], 400);
        }

        $emailExists = $this->userRepository->findOneBy(['email' => $email]);
        if ($emailExists) {
            $this->AxiomLogger->warning('User creation - email already in use', [
                'email' => $email,
            ]);
            return new JsonResponse([
                'status'  => false,
                'message' => 'This email is already in use'
            ], 409);
        }

        if ($availabilityStart || $availabilityEnd) {
            $today = new \DateTimeImmutable();

            if ($availabilityStart) {
                $startDate = new \DateTimeImmutable($availabilityStart);
                if ($startDate < $today) {
                    $this->AxiomLogger->warning('User creation - availabilityStart in the past', [
                        'email' => $email,
                        'value' => $availabilityStart,
                    ]);
                    return new JsonResponse([
                        'status'  => false,
                        'message' => 'La date de début de disponibilité doit être dans le futur'
                    ], 400);
                }
            }

            if ($availabilityEnd) {
                $endDate = new \DateTimeImmutable($availabilityEnd);
                if ($endDate < $today) {
                    $this->AxiomLogger->warning('User creation - availabilityEnd in the past', [
                        'email' => $email,
                        'value' => $availabilityEnd,
                    ]);
                    return new JsonResponse([
                        'status'  => false,
                        'message' => 'La date de fin de disponibilité doit être dans le futur'
                    ], 400);
                }
            }

            if ($availabilityStart && $availabilityEnd && $endDate < $startDate) {
                $this->AxiomLogger->warning('User creation - endDate before startDate', [
                    'email'             => $email,
                    'availabilityStart' => $availabilityStart,
                    'availabilityEnd'   => $availabilityEnd,
                ]);
                return new JsonResponse([
                    'status'  => false,
                    'message' => 'La date de fin doit être après la date de début'
                ], 400);
            }
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

        $this->AxiomLogger->info('✅ User created successfully', [
            'user_id' => $user->getId(),
            'email'   => $user->getEmail(),
        ]);

        return new JsonResponse([
            'status'  => true,
            'message' => 'User created successfully',
            'user'    => [
                'id'                => $user->getId(),
                'email'             => $user->getEmail(),
                'firstName'         => $user->getFirstName(),
                'lastName'          => $user->getLastName(),
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd'   => $user->getAvailabilityEnd()?->format('Y-m-d'),
                'skills'            => $user->getSkills(),
            ]
        ], 201);
    }

    #[Route('/api/getAllUsers', name: 'get_all_users', methods: ['GET'])]
    public function getAllUsers(): JsonResponse
    {
        $users = $this->userRepository->findAll();

        $this->AxiomLogger->info('All users fetched', [
            'count' => count($users),
        ]);

        $result = array_map(fn(User $user) => [
            'id'                => $user->getId(),
            'email'             => $user->getEmail(),
            'firstName'         => $user->getFirstName(),
            'lastName'          => $user->getLastName(),
            'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
            'availabilityEnd'   => $user->getAvailabilityEnd()?->format('Y-m-d'),
        ], $users);

        return new JsonResponse($result);
    }

    #[Route('/api/getConnectedUser', name: 'get_connected_user', methods: ['GET'])]
    public function getConnectedUser(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user) {
            $this->AxiomLogger->warning('getConnectedUser - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        if (!$user instanceof User) {
            $userEntity = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
            if (!$userEntity) {
                $this->AxiomLogger->warning('getConnectedUser - user entity not found', [
                    'identifier' => $user->getUserIdentifier(),
                ]);
                return new JsonResponse(['message' => 'User entity not found'], 404);
            }
            $user = $userEntity;
        }

        $userData = $this->userService->findAll($user);

        $this->AxiomLogger->info('Connected user fetched', [
            'user_id' => $user->getId(),
        ]);

        return new JsonResponse([
            'id'                => $user->getId(),
            'email'             => $user->getEmail(),
            'firstName'         => $user->getFirstName(),
            'lastName'          => $user->getLastName(),
            'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
            'availabilityEnd'   => $user->getAvailabilityEnd()?->format('Y-m-d'),
            'userData'          => $userData,
        ]);
    }

    #[Route('/api/user/skills', name: 'api_user_skills', methods: ['GET'])]
    public function getUserSkills(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('getUserSkills - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $skills = $user->getSkills();

            $this->AxiomLogger->info('User skills fetched', [
                'user_id' => $user->getId(),
                'count'   => count($skills),
            ]);

            $data = [];
            foreach ($skills as $skill) {
                $data[] = [
                    'id'              => $skill->getId(),
                    'name'            => $skill->getName(),
                    'description'     => $skill->getDescription(),
                    'duree'           => $skill->getDuree() ? $skill->getDuree()->format('Y-m-d') : null,
                    'technoUtilisees' => $skill->getTechnoUtilisees(),
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error fetching user skills', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse([
                'error'   => 'Error fetching user skills',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/skills', name: 'api_skills', methods: ['GET'])]
    public function getAllSkills(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('getAllSkills - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $skillsRepo = $this->manager->getRepository(Skills::class);
            $allSkills  = $skillsRepo->findAll();

            $this->AxiomLogger->info('All skills fetched', [
                'user_id' => $user->getId(),
                'count'   => count($allSkills),
            ]);

            $data = [];
            foreach ($allSkills as $skill) {
                try {
                    $skillData = [
                        'id'              => $skill->getId(),
                        'name'            => $skill->getName()            ?? 'N/A',
                        'description'     => $skill->getDescription()     ?? 'N/A',
                        'duree'           => null,
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
                    $this->AxiomLogger->warning('Error processing skill', [
                        'skill_id' => $skill->getId(),
                        'error'    => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error fetching all skills', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse([
                'error'   => 'Error fetching all skills',
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => basename($e->getFile())
            ], 500);
        }
    }

    #[Route('/api/skills/create', name: 'api_create_skill', methods: ['POST'])]
    public function createSkill(Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('createSkill - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $data = json_decode($request->getContent(), true);

            $this->AxiomLogger->info('Skill creation attempt', [
                'user_id'    => $user->getId(),
                'skill_name' => $data['name'] ?? null,
            ]);

            if (empty($data['name'])) {
                $this->AxiomLogger->warning('createSkill - missing name', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'Skill name is required'], 400);
            }

            if (empty($data['description'])) {
                $this->AxiomLogger->warning('createSkill - missing description', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'Description is required'], 400);
            }

            if (empty($data['technoUtilisees'])) {
                $this->AxiomLogger->warning('createSkill - missing technoUtilisees', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'Technologies used is required'], 400);
            }

            if (empty($data['duree'])) {
                $this->AxiomLogger->warning('createSkill - missing duree', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'Duration is required'], 400);
            }

            $skillsRepo    = $this->manager->getRepository(Skills::class);
            $existingSkill = $skillsRepo->findOneBy(['Name' => $data['name']]);

            if ($existingSkill) {
                $this->AxiomLogger->warning('createSkill - skill name already exists', [
                    'user_id'    => $user->getId(),
                    'skill_name' => $data['name'],
                ]);
                return new JsonResponse(['success' => false, 'message' => 'A skill with this name already exists'], 409);
            }

            $skill = new Skills();
            $skill->setName($data['name']);
            $skill->setDescription($data['description']);
            $skill->setTechnoUtilisees($data['technoUtilisees']);

            try {
                $duree = new \DateTimeImmutable($data['duree']);
                $skill->setDuree($duree);
            } catch (\Exception $e) {
                $this->AxiomLogger->warning('createSkill - invalid duree format', [
                    'user_id' => $user->getId(),
                    'value'   => $data['duree'],
                    'error'   => $e->getMessage(),
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
            }

            $this->manager->persist($skill);
            $this->manager->flush();

            $this->AxiomLogger->info('✅ Skill created', [
                'user_id'    => $user->getId(),
                'skill_id'   => $skill->getId(),
                'skill_name' => $skill->getName(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Skill created successfully',
                'skill'   => [
                    'id'              => $skill->getId(),
                    'name'            => $skill->getName(),
                    'description'     => $skill->getDescription(),
                    'technoUtilisees' => $skill->getTechnoUtilisees(),
                    'duree'           => $skill->getDuree()->format('Y-m-d')
                ]
            ], 201);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error creating skill', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse([
                'success' => false,
                'error'   => 'Error creating skill',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/skills/update/{id}', name: 'api_update_skill', methods: ['PUT', 'PATCH'])]
    public function updateSkill(int $id, Request $request, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('updateSkill - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $skillsRepo = $this->manager->getRepository(Skills::class);
            $skill      = $skillsRepo->find($id);

            if (!$skill) {
                $this->AxiomLogger->warning('updateSkill - skill not found', [
                    'user_id'  => $user->getId(),
                    'skill_id' => $id,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Skill not found'], 404);
            }

            $data = json_decode($request->getContent(), true);

            $this->AxiomLogger->info('Skill update attempt', [
                'user_id'  => $user->getId(),
                'skill_id' => $id,
                'fields'   => array_keys($data),
            ]);

            if (isset($data['name'])) {
                $existingSkill = $skillsRepo->findOneBy(['Name' => $data['name']]);
                if ($existingSkill && $existingSkill->getId() !== $id) {
                    $this->AxiomLogger->warning('updateSkill - name already exists', [
                        'user_id'    => $user->getId(),
                        'skill_id'   => $id,
                        'skill_name' => $data['name'],
                    ]);
                    return new JsonResponse(['success' => false, 'message' => 'A skill with this name already exists'], 409);
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
                    $skill->setDuree(new \DateTimeImmutable($data['duree']));
                } catch (\Exception $e) {
                    $this->AxiomLogger->warning('updateSkill - invalid duree format', [
                        'user_id'  => $user->getId(),
                        'skill_id' => $id,
                        'value'    => $data['duree'],
                        'error'    => $e->getMessage(),
                    ]);
                    return new JsonResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
                }
            }

            $this->manager->flush();

            $this->AxiomLogger->info('✅ Skill updated', [
                'user_id'  => $user->getId(),
                'skill_id' => $skill->getId(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Skill updated successfully',
                'skill'   => [
                    'id'              => $skill->getId(),
                    'name'            => $skill->getName(),
                    'description'     => $skill->getDescription(),
                    'technoUtilisees' => $skill->getTechnoUtilisees(),
                    'duree'           => $skill->getDuree()->format('Y-m-d')
                ]
            ]);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error updating skill', [
                'user_id'  => $user->getId(),
                'skill_id' => $id,
                'error'    => $e->getMessage(),
            ]);
            return new JsonResponse([
                'success' => false,
                'error'   => 'Error updating skill',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/skills/delete/{id}', name: 'api_delete_skill', methods: ['DELETE'])]
    public function deleteSkill(int $id, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('deleteSkill - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $skillsRepo = $this->manager->getRepository(Skills::class);
            $skill      = $skillsRepo->find($id);

            if (!$skill) {
                $this->AxiomLogger->warning('deleteSkill - skill not found', [
                    'user_id'  => $user->getId(),
                    'skill_id' => $id,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Skill not found'], 404);
            }

            $skillName = $skill->getName();

            $this->manager->remove($skill);
            $this->manager->flush();

            $this->AxiomLogger->info('✅ Skill deleted', [
                'user_id'    => $user->getId(),
                'skill_id'   => $id,
                'skill_name' => $skillName,
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => "Skill '$skillName' deleted successfully"
            ]);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error deleting skill', [
                'user_id'  => $user->getId(),
                'skill_id' => $id,
                'error'    => $e->getMessage(),
            ]);
            return new JsonResponse([
                'success' => false,
                'error'   => 'Error deleting skill',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/api/user/add/skills', name: 'api_user_skills_add', methods: ['POST'])]
    public function addUserSkill(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            $this->AxiomLogger->warning('addUserSkill - user not authenticated');
            return new JsonResponse(['message' => 'Utilisateur non connecté'], 401);
        }

        try {
            $data    = json_decode($request->getContent(), true);
            $skillId = $data['skill_id'] ?? null;

            if (!$skillId) {
                $this->AxiomLogger->warning('addUserSkill - missing skill_id', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'ID de compétence manquant'], 400);
            }

            $skill = $em->getRepository(Skills::class)->find($skillId);
            if (!$skill) {
                $this->AxiomLogger->warning('addUserSkill - skill not found', [
                    'user_id'  => $user->getId(),
                    'skill_id' => $skillId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Compétence non trouvée'], 404);
            }

            if ($user->getSkills()->contains($skill)) {
                $this->AxiomLogger->warning('addUserSkill - skill already added', [
                    'user_id'  => $user->getId(),
                    'skill_id' => $skillId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Vous avez déjà cette compétence'], 400);
            }

            $user->addSkill($skill);
            $em->persist($user);
            $em->flush();

            $this->AxiomLogger->info('✅ Skill added to user', [
                'user_id'    => $user->getId(),
                'skill_id'   => $skill->getId(),
                'skill_name' => $skill->getName(),
            ]);

            return new JsonResponse([
                'success'    => true,
                'message'    => 'Compétence ajoutée avec succès',
                'skill_name' => $skill->getName()
            ], 201);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error adding skill to user', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/user/delete/skill', name: 'api_user_skill_delete', methods: ['DELETE'])]
    public function removeUserSkill(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            $this->AxiomLogger->warning('removeUserSkill - user not authenticated');
            return new JsonResponse(['message' => 'Utilisateur non connecté'], 401);
        }

        try {
            $data    = json_decode($request->getContent(), true);
            $skillId = $data['skill_id'] ?? null;

            if (!$skillId) {
                $this->AxiomLogger->warning('removeUserSkill - missing skill_id', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'ID de compétence manquant'], 400);
            }

            $skill = $em->getRepository(Skills::class)->find($skillId);
            if (!$skill) {
                $this->AxiomLogger->warning('removeUserSkill - skill not found', [
                    'user_id'  => $user->getId(),
                    'skill_id' => $skillId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Compétence non trouvée'], 404);
            }

            if (!$user->getSkills()->contains($skill)) {
                $this->AxiomLogger->warning('removeUserSkill - user does not have this skill', [
                    'user_id'  => $user->getId(),
                    'skill_id' => $skillId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Vous ne possédez pas cette compétence'], 400);
            }

            $user->removeSkill($skill);
            $em->flush();

            $this->AxiomLogger->info('✅ Skill removed from user', [
                'user_id'    => $user->getId(),
                'skill_id'   => $skill->getId(),
                'skill_name' => $skill->getName(),
            ]);

            return new JsonResponse([
                'success'    => true,
                'message'    => 'Compétence retirée avec succès',
                'skill_name' => $skill->getName()
            ], 200);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error removing skill from user', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/user/availability', name: 'api_user_availability_change', methods: ['POST'])]
    public function changeAvailability(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            $this->AxiomLogger->warning('changeAvailability - user not authenticated');
            return new JsonResponse(['message' => 'Utilisateur non connecté'], 401);
        }

        try {
            $data              = json_decode($request->getContent(), true);
            $availabilityStart = $data['availabilityStart'] ?? null;
            $availabilityEnd   = $data['availabilityEnd']   ?? null;

            if (!$availabilityStart || !$availabilityEnd) {
                $this->AxiomLogger->warning('changeAvailability - missing dates', [
                    'user_id' => $user->getId(),
                ]);
                return new JsonResponse(['success' => false, 'message' => 'availability not sended'], 400);
            }

            try {
                $startDate = new \DateTimeImmutable($availabilityStart);
            } catch (\Exception $e) {
                $this->AxiomLogger->warning('changeAvailability - invalid availabilityStart', [
                    'user_id' => $user->getId(),
                    'value'   => $availabilityStart,
                    'error'   => $e->getMessage(),
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Invalid availabilityStart date format'], 400);
            }

            try {
                $endDate = new \DateTimeImmutable($availabilityEnd);
            } catch (\Exception $e) {
                $this->AxiomLogger->warning('changeAvailability - invalid availabilityEnd', [
                    'user_id' => $user->getId(),
                    'value'   => $availabilityEnd,
                    'error'   => $e->getMessage(),
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Invalid availabilityEnd date format'], 400);
            }

            $user->setAvailabilityStart($startDate);
            $user->setAvailabilityEnd($endDate);
            $em->persist($user);
            $em->flush();

            $this->AxiomLogger->info('✅ Availability updated', [
                'user_id'           => $user->getId(),
                'availabilityStart' => $availabilityStart,
                'availabilityEnd'   => $availabilityEnd,
            ]);

            return new JsonResponse([
                'success'           => true,
                'message'           => 'Availability updated successfully',
                'availabilityStart' => $user->getAvailabilityStart()?->format('Y-m-d'),
                'availabilityEnd'   => $user->getAvailabilityEnd()?->format('Y-m-d'),
            ], 200);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error updating availability', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/user/projects', name: 'api_user_projects', methods: ['GET'])]
    public function getUserProjects(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('getUserProjects - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $projects = $user->getProject();

            $this->AxiomLogger->info('User projects fetched', [
                'user_id' => $user->getId(),
                'count'   => count($projects),
            ]);

            $data = [];
            foreach ($projects as $project) {
                $data[] = [
                    'id'             => $project->getId(),
                    'name'           => $project->getName(),
                    'description'    => $project->getDescription(),
                    'requiredSkills' => $project->getRequiredSkills(),
                    'startDate'      => $project->getStartDate()?->format('Y-m-d'),
                    'endDate'        => $project->getEndDate()?->format('Y-m-d'),
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error fetching user projects', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'Error fetching user projects', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/user/add/project', name: 'api_user_add_project', methods: ['POST'])]
    public function addUserToProject(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('addUserToProject - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $data      = json_decode($request->getContent(), true);
            $projectId = $data['project_id'] ?? null;

            if (!$projectId) {
                $this->AxiomLogger->warning('addUserToProject - missing project_id', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'Project ID required'], 400);
            }

            $project = $em->getRepository(Project::class)->find($projectId);
            if (!$project) {
                $this->AxiomLogger->warning('addUserToProject - project not found', [
                    'user_id'    => $user->getId(),
                    'project_id' => $projectId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Project not found'], 404);
            }

            if ($user->getProject()->contains($project)) {
                $this->AxiomLogger->warning('addUserToProject - user already in project', [
                    'user_id'    => $user->getId(),
                    'project_id' => $projectId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Already in this project'], 400);
            }

            $user->addProject($project);
            $em->flush();

            $this->AxiomLogger->info('✅ User added to project', [
                'user_id'      => $user->getId(),
                'project_id'   => $project->getId(),
                'project_name' => $project->getName(),
            ]);

            return new JsonResponse([
                'success'      => true,
                'project_name' => $project->getName(),
                'message'      => 'Added to project successfully'
            ], 200);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error adding user to project', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/user/delete/project', name: 'api_user_delete_project', methods: ['DELETE'])]
    public function removeUserFromProject(Request $request, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('removeUserFromProject - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $data      = json_decode($request->getContent(), true);
            $projectId = $data['project_id'] ?? null;

            if (!$projectId) {
                $this->AxiomLogger->warning('removeUserFromProject - missing project_id', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'Project ID required'], 400);
            }

            $project = $em->getRepository(Project::class)->find($projectId);
            if (!$project) {
                $this->AxiomLogger->warning('removeUserFromProject - project not found', [
                    'user_id'    => $user->getId(),
                    'project_id' => $projectId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Project not found'], 404);
            }

            if (!$user->getProject()->contains($project)) {
                $this->AxiomLogger->warning('removeUserFromProject - user not in project', [
                    'user_id'    => $user->getId(),
                    'project_id' => $projectId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'User is not in this project'], 400);
            }

            $user->removeUserProject($project);
            $em->flush();

            $this->AxiomLogger->info('✅ User removed from project', [
                'user_id'      => $user->getId(),
                'project_id'   => $project->getId(),
                'project_name' => $project->getName(),
            ]);

            return new JsonResponse([
                'success'      => true,
                'project_name' => $project->getName(),
                'message'      => 'Removed from project successfully'
            ], 200);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error removing user from project', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/invitations/received', name: 'app_received_invitations', methods: ['GET'])]
    public function getReceivedInvitations(Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            $this->AxiomLogger->warning('getReceivedInvitations - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $received = [];
        foreach ($user->getReceivedInvitations() as $sender) {
            if (!$user->getFriends()->contains($sender)) {
                $received[] = [
                    'id'        => $sender->getId(),
                    'firstName' => $sender->getFirstName(),
                    'lastName'  => $sender->getLastName(),
                    'email'     => $sender->getEmail(),
                ];
            }
        }

        $this->AxiomLogger->info('Received invitations fetched', [
            'user_id' => $user->getId(),
            'count'   => count($received),
        ]);

        return new JsonResponse($received);
    }

    #[Route('/api/invitations/sent', name: 'app_sent_invitations', methods: ['GET'])]
    public function getSentInvitations(Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            $this->AxiomLogger->warning('getSentInvitations - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        $data = [];
        foreach ($user->getSentInvitations() as $invitedUser) {
            if (!$user->getFriends()->contains($invitedUser)) {
                $data[] = [
                    'id'        => $invitedUser->getId(),
                    'firstName' => $invitedUser->getFirstName(),
                    'lastName'  => $invitedUser->getLastName(),
                    'email'     => $invitedUser->getEmail(),
                ];
            }
        }

        $this->AxiomLogger->info('Sent invitations fetched', [
            'user_id' => $user->getId(),
            'count'   => count($data),
        ]);

        return new JsonResponse($data);
    }

    #[Route('/api/user/friends', name: 'api_user_friends', methods: ['GET'])]
    public function getUserFriends(Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            $this->AxiomLogger->warning('getUserFriends - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $friends = $user->getFriends();

            $this->AxiomLogger->info('User friends fetched', [
                'user_id' => $user->getId(),
                'count'   => count($friends),
            ]);

            $data = [];
            foreach ($friends as $friend) {
                $data[] = [
                    'id'        => $friend->getId(),
                    'firstName' => $friend->getFirstName(),
                    'lastName'  => $friend->getLastName(),
                    'email'     => $friend->getEmail(),
                ];
            }

            return new JsonResponse($data);
        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error fetching user friends', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['error' => 'Error fetching user friends', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/delete/friends/{id}', name: 'api_delete_friend', methods: ['DELETE'])]
    public function deleteFriend(int $id, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('deleteFriend - user not authenticated');
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $friend = $em->getRepository(User::class)->find($id);
        if (!$friend) {
            $this->AxiomLogger->warning('deleteFriend - friend not found', [
                'user_id'   => $user->getId(),
                'friend_id' => $id,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Ami introuvable'], 404);
        }

        if (!$user->getFriends()->contains($friend)) {
            $this->AxiomLogger->warning('deleteFriend - not in friend list', [
                'user_id'   => $user->getId(),
                'friend_id' => $id,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Cet utilisateur n\'est pas dans votre liste d\'amis'], 400);
        }

        $user->removeFriend($friend);
        $friend->removeFriend($user);
        $em->flush();

        $this->AxiomLogger->info('✅ Friend removed', [
            'user_id'   => $user->getId(),
            'friend_id' => $id,
        ]);

        return new JsonResponse(['success' => true, 'message' => 'Ami supprimé avec succès']);
    }

    #[Route('api/send/invitation', name: 'api_send_invitation', methods: ['POST'])]
    public function sendInvitation(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            $this->AxiomLogger->warning('sendInvitation - user not authenticated');
            return new JsonResponse(['message' => 'User not authenticated'], 401);
        }

        try {
            $data     = json_decode($request->getContent(), true);
            $friendId = $data['friend_id'] ?? null;

            if (!$friendId) {
                $this->AxiomLogger->warning('sendInvitation - missing friend_id', ['user_id' => $user->getId()]);
                return new JsonResponse(['success' => false, 'message' => 'Friend ID required'], 400);
            }

            $friend = $em->getRepository(User::class)->find($friendId);
            if (!$friend) {
                $this->AxiomLogger->warning('sendInvitation - target user not found', [
                    'user_id'   => $user->getId(),
                    'friend_id' => $friendId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
            }

            if ($user->getFriends()->contains($friend)) {
                $this->AxiomLogger->warning('sendInvitation - already friends', [
                    'user_id'   => $user->getId(),
                    'friend_id' => $friendId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Vous êtes déjà amis'], 400);
            }

            if ($user->getSentInvitations()->contains($friend)) {
                $this->AxiomLogger->warning('sendInvitation - invitation already sent', [
                    'user_id'   => $user->getId(),
                    'friend_id' => $friendId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Invitation déjà envoyée'], 400);
            }

            $user->addSentInvitation($friend);
            $em->flush();

            $this->AxiomLogger->info('✅ Invitation sent', [
                'user_id'   => $user->getId(),
                'friend_id' => $friendId,
            ]);

            return new JsonResponse(['success' => true, 'message' => 'Invitation envoyée avec succès'], 200);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error sending invitation', [
                'user_id' => $user->getId(),
                'error'   => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/invitations/accept/{senderId}', name: 'api_accept_invitation', methods: ['POST'])]
    public function acceptInvitation(int $senderId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('acceptInvitation - user not authenticated');
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $sender = $em->getRepository(User::class)->find($senderId);
        if (!$sender) {
            $this->AxiomLogger->warning('acceptInvitation - sender not found', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur expéditeur non trouvé'], 404);
        }

        if (!$user->getReceivedInvitations()->contains($sender)) {
            $this->AxiomLogger->warning('acceptInvitation - no invitation found', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Aucune invitation trouvée.'], 404);
        }

        try {
            $user->getReceivedInvitations()->removeElement($sender);
            $sender->getSentInvitations()->removeElement($user);

            $user->addFriend($sender);
            $sender->addFriend($user);

            $em->persist($user);
            $em->persist($sender);
            $em->flush();

            $this->AxiomLogger->info('✅ Invitation accepted', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
            ]);

            return new JsonResponse(['success' => true, 'message' => 'Invitation acceptée. Vous êtes maintenant amis.']);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error accepting invitation', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
                'error'     => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/invitations/delete-received/{senderId}', name: 'api_delete_received_invitation', methods: ['DELETE'])]
    public function deleteReceivedInvitation(int $senderId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('deleteReceivedInvitation - user not authenticated');
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $sender = $em->getRepository(User::class)->find($senderId);
        if (!$sender) {
            $this->AxiomLogger->warning('deleteReceivedInvitation - sender not found', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur expéditeur non trouvé'], 404);
        }

        if (!$user->getReceivedInvitations()->contains($sender)) {
            $this->AxiomLogger->warning('deleteReceivedInvitation - invitation not found', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Invitation non trouvée'], 404);
        }

        try {
            $user->getReceivedInvitations()->removeElement($sender);
            $sender->getSentInvitations()->removeElement($user);

            $em->persist($user);
            $em->persist($sender);
            $em->flush();

            $this->AxiomLogger->info('✅ Received invitation deleted', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
            ]);

            return new JsonResponse(['success' => true, 'message' => 'Invitation reçue supprimée avec succès']);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error deleting received invitation', [
                'user_id'   => $user->getId(),
                'sender_id' => $senderId,
                'error'     => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/api/invitations/delete-sent/{receiverId}', name: 'api_delete_sent_invitation', methods: ['DELETE'])]
    public function deleteSentInvitation(int $receiverId, Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user instanceof User) {
            $this->AxiomLogger->warning('deleteSentInvitation - user not authenticated');
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur non authentifié'], 401);
        }

        $receiver = $em->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            $this->AxiomLogger->warning('deleteSentInvitation - receiver not found', [
                'user_id'     => $user->getId(),
                'receiver_id' => $receiverId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur destinataire non trouvé'], 404);
        }

        if (!$user->getSentInvitations()->contains($receiver)) {
            $this->AxiomLogger->warning('deleteSentInvitation - invitation not found', [
                'user_id'     => $user->getId(),
                'receiver_id' => $receiverId,
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Invitation non trouvée'], 404);
        }

        try {
            $user->getSentInvitations()->removeElement($receiver);
            $receiver->getReceivedInvitations()->removeElement($user);

            $em->persist($user);
            $em->persist($receiver);
            $em->flush();

            $this->AxiomLogger->info('✅ Sent invitation deleted', [
                'user_id'     => $user->getId(),
                'receiver_id' => $receiverId,
            ]);

            return new JsonResponse(['success' => true, 'message' => 'Invitation envoyée supprimée avec succès']);

        } catch (\Exception $e) {
            $this->AxiomLogger->error('Error deleting sent invitation', [
                'user_id'     => $user->getId(),
                'receiver_id' => $receiverId,
                'error'       => $e->getMessage(),
            ]);
            return new JsonResponse(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }
}