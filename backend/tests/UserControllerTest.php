<?php

namespace App\Tests\Controller;

use App\Entity\Skills;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserControllerTest extends WebTestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function createAuthenticatedClient(
        string $email    = 'phpunit@openhub.com',
        string $password = 'TestPass!123'
    ): \Symfony\Bundle\FrameworkBundle\KernelBrowser {
        $client = static::createClient();
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            foreach ($existing->getSkills() as $s)    { $existing->removeSkill($s); }
            foreach ($existing->getProject() as $p)   { $existing->removeProject($p); }
            foreach ($existing->getFriends() as $f)   { $existing->removeFriend($f); }
            $em->remove($existing);
            $em->flush();
            $em->clear();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setFirstName('Jean');
        $user->setLastName('Dupont');
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        $client->request(
            'POST', '/api/login_check', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $response = json_decode($client->getResponse()->getContent(), true);
        $token    = $response['token'] ?? $response['access_token'] ?? null;

        if (empty($token)) {
            throw new \RuntimeException(
                sprintf(
                    "JWT introuvable. Status: %d, Réponse: %s",
                    $client->getResponse()->getStatusCode(),
                    $client->getResponse()->getContent()
                )
            );
        }

        $client->setServerParameter('HTTP_Authorization', 'Bearer ' . $token);

        return $client;
    }

    private function getTestUser(string $email = 'phpunit@openhub.com'): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        return $em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function createSkill(
        string $name        = 'Symfony',
        string $description = 'Framework PHP',
        string $techno      = 'PHP',
        string $duree       = '2023-01-01'
    ): Skills {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $existing = $em->getRepository(Skills::class)->findOneBy(['Name' => $name]);
        if ($existing) {
            return $existing;
        }

        $skill = new Skills();
        $skill->setName($name);
        $skill->setDescription($description);
        $skill->setTechnoUtilisees($techno);
        $skill->setDuree(new \DateTimeImmutable($duree));
        $em->persist($skill);
        $em->flush();

        return $skill;
    }

    private function createSecondUser(
        string $email    = 'phpunit2@openhub.com',
        string $password = 'TestPass!123'
    ): User {
        $em     = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $existing;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $user->setFirstName('Marie');
        $user->setLastName('Martin');
        $user->setRoles(['ROLE_USER']);
        $em->persist($user);
        $em->flush();

        return $user;
    }

    // =========================================================================
    // POST /api/userCreate
    // =========================================================================

    public function testUserCreateSuccess(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/userCreate', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'     => 'nouveau@openhub.com',
                'password'  => 'MonMotDePasse123!',
                'firstName' => 'Alice',
                'lastName'  => 'Durand',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['status']);
        $this->assertSame('User created successfully', $data['message']);
        $this->assertSame('nouveau@openhub.com', $data['user']['email']);
        $this->assertSame('Alice', $data['user']['firstName']);

        // Nettoyage
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'nouveau@openhub.com']);
        if ($user) { $em->remove($user); $em->flush(); }
    }

    public function testUserCreateReturns400WhenMissingEmailOrPassword(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/userCreate', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['firstName' => 'Alice'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertSame('Email and password are required', $data['message']);
    }

    public function testUserCreateReturns409WhenEmailAlreadyInUse(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/userCreate', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'    => 'phpunit@openhub.com',
                'password' => 'AutreMotDePasse!',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertSame('This email is already in use', $data['message']);
    }

    public function testUserCreateReturns400WhenAvailabilityStartInPast(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/userCreate', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'             => 'test-past@openhub.com',
                'password'          => 'MonMotDePasse123!',
                'availabilityStart' => '2020-01-01',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['status']);
        $this->assertStringContainsString('futur', $data['message']);
    }

    // =========================================================================
    // GET /api/getAllUsers
    // =========================================================================

    public function testGetAllUsersReturnsArray(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/getAllUsers');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('email', $first);
        $this->assertArrayHasKey('firstName', $first);
        $this->assertArrayHasKey('lastName', $first);
    }

    // =========================================================================
    // GET /api/getConnectedUser
    // =========================================================================

    public function testGetConnectedUserReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/getConnectedUser');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetConnectedUserReturnsCurrentUser(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/getConnectedUser');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('phpunit@openhub.com', $data['email']);
        $this->assertSame('Jean', $data['firstName']);
        $this->assertSame('Dupont', $data['lastName']);
    }

    // =========================================================================
    // GET /api/skills
    // =========================================================================

    public function testGetAllSkillsReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/skills');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetAllSkillsReturnsArray(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->createSkill('Symfony', 'Framework PHP', 'PHP', '2023-01-01');

        $client->request('GET', '/api/skills');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));

        $names = array_column($data, 'name');
        $this->assertContains('Symfony', $names);
    }

    // =========================================================================
    // GET /api/user/skills
    // =========================================================================

    public function testGetUserSkillsReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/user/skills');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetUserSkillsReturnsUserSkills(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('React', 'Librairie JS', 'JavaScript', '2023-06-01');

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addSkill($skill);
        $em->flush();

        $client->request('GET', '/api/user/skills');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data  = json_decode($client->getResponse()->getContent(), true);
        $names = array_column($data, 'name');
        $this->assertContains('React', $names);
    }

    // =========================================================================
    // POST /api/skills/create
    // =========================================================================

    public function testCreateSkillReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/skills/create', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Docker'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateSkillReturns400WhenMissingName(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/skills/create', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'description'     => 'Une description',
                'technoUtilisees' => 'Docker',
                'duree'           => '2024-01-01',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Skill name is required', $data['message']);
    }

    public function testCreateSkillReturns409WhenNameAlreadyExists(): void
    {
        $client = $this->createAuthenticatedClient();
        $this->createSkill('Docker', 'Conteneurisation', 'Docker', '2023-01-01');

        $client->request(
            'POST', '/api/skills/create', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name'            => 'Docker',
                'description'     => 'Autre description',
                'technoUtilisees' => 'Docker',
                'duree'           => '2024-01-01',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('A skill with this name already exists', $data['message']);
    }

    public function testCreateSkillSuccess(): void
    {
        $client    = $this->createAuthenticatedClient();
        $skillName = 'Kubernetes_' . uniqid();

        $client->request(
            'POST', '/api/skills/create', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name'            => $skillName,
                'description'     => 'Orchestration de conteneurs',
                'technoUtilisees' => 'K8s',
                'duree'           => '2024-03-15',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame($skillName, $data['skill']['name']);
        $this->assertSame('2024-03-15', $data['skill']['duree']);
    }

    // =========================================================================
    // PUT /api/skills/update/{id}
    // =========================================================================

    public function testUpdateSkillReturns404WhenNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'PUT', '/api/skills/update/999999', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'NouveauNom'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Skill not found', $data['message']);
    }

    public function testUpdateSkillSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('Vue.js_' . uniqid(), 'Framework JS', 'JavaScript', '2023-01-01');

        $client->request(
            'PUT', '/api/skills/update/' . $skill->getId(), [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'description' => 'Framework JavaScript progressif',
                'duree'       => '2024-06-01',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Skill updated successfully', $data['message']);
        $this->assertSame('2024-06-01', $data['skill']['duree']);
    }

    public function testUpdateSkillReturns400WhenInvalidDate(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('Angular_' . uniqid(), 'Framework', 'TypeScript', '2023-01-01');

        $client->request(
            'PATCH', '/api/skills/update/' . $skill->getId(), [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['duree' => 'date-invalide'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Invalid date format', $data['message']);
    }

    // =========================================================================
    // DELETE /api/skills/delete/{id}
    // =========================================================================

    public function testDeleteSkillReturns404WhenNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', '/api/skills/delete/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Skill not found', $data['message']);
    }

    public function testDeleteSkillSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('ToDelete_' . uniqid(), 'A supprimer', 'PHP', '2023-01-01');

        $client->request('DELETE', '/api/skills/delete/' . $skill->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);

        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $deleted = $em->getRepository(Skills::class)->find($skill->getId());
        $this->assertNull($deleted);
    }

    // =========================================================================
    // POST /api/user/add/skills
    // =========================================================================

    public function testAddUserSkillReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/user/add/skills', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['skill_id' => 1])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAddUserSkillReturns400WhenMissingSkillId(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/user/add/skills', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('ID de compétence manquant', $data['message']);
    }

    public function testAddUserSkillReturns404WhenSkillNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/user/add/skills', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['skill_id' => 999999])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Compétence non trouvée', $data['message']);
    }

    public function testAddUserSkillReturns400WhenAlreadyAdded(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('Laravel_' . uniqid(), 'Framework PHP', 'PHP', '2023-01-01');

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addSkill($skill);
        $em->flush();

        $client->request(
            'POST', '/api/user/add/skills', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['skill_id' => $skill->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Vous avez déjà cette compétence', $data['message']);
    }

    public function testAddUserSkillSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('Python_' . uniqid(), 'Langage de script', 'Python', '2023-01-01');

        $client->request(
            'POST', '/api/user/add/skills', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['skill_id' => $skill->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Compétence ajoutée avec succès', $data['message']);
    }

    // =========================================================================
    // DELETE /api/user/delete/skill
    // =========================================================================

    public function testRemoveUserSkillReturns400WhenUserDoesNotHaveSkill(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('Node_' . uniqid(), 'Runtime JS', 'JavaScript', '2023-01-01');

        $client->request(
            'DELETE', '/api/user/delete/skill', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['skill_id' => $skill->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Vous ne possédez pas cette compétence', $data['message']);
    }

    public function testRemoveUserSkillSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $skill  = $this->createSkill('Go_' . uniqid(), 'Langage Go', 'Golang', '2023-01-01');

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addSkill($skill);
        $em->flush();

        $client->request(
            'DELETE', '/api/user/delete/skill', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['skill_id' => $skill->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Compétence retirée avec succès', $data['message']);
    }

    // =========================================================================
    // POST /api/user/availability
    // =========================================================================

    public function testChangeAvailabilityReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/user/availability', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['availabilityStart' => '2025-01-01', 'availabilityEnd' => '2025-06-01'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testChangeAvailabilityReturns400WhenMissingDates(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/user/availability', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['availabilityStart' => '2025-01-01'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('availability not sended', $data['message']);
    }

    public function testChangeAvailabilitySuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/user/availability', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'availabilityStart' => '2025-09-01',
                'availabilityEnd'   => '2025-12-31',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('2025-09-01', $data['availabilityStart']);
        $this->assertSame('2025-12-31', $data['availabilityEnd']);
    }

    // =========================================================================
    // POST /api/send/invitation
    // =========================================================================

    public function testSendInvitationReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/send/invitation', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['friend_id' => 1])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testSendInvitationReturns400WhenMissingFriendId(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/send/invitation', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Friend ID required', $data['message']);
    }

    public function testSendInvitationReturns404WhenUserNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/send/invitation', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['friend_id' => 999999])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('User not found', $data['message']);
    }

    public function testSendInvitationSuccess(): void
    {
        $client  = $this->createAuthenticatedClient();
        $friend  = $this->createSecondUser();

        $client->request(
            'POST', '/api/send/invitation', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['friend_id' => $friend->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Invitation envoyée avec succès', $data['message']);
    }

    public function testSendInvitationReturns400WhenAlreadySent(): void
    {
        $client = $this->createAuthenticatedClient();
        $friend = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addSentInvitation($friend);
        $em->flush();

        $client->request(
            'POST', '/api/send/invitation', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['friend_id' => $friend->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Invitation déjà envoyée', $data['message']);
    }

    // =========================================================================
    // GET /api/invitations/received
    // =========================================================================

    public function testGetReceivedInvitationsReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/invitations/received');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetReceivedInvitationsReturnsArray(): void
    {
        $client = $this->createAuthenticatedClient();
        $sender = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $sender->addSentInvitation($user);
        $em->flush();

        $client->request('GET', '/api/invitations/received');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $emails = array_column($data, 'email');
        $this->assertContains('phpunit2@openhub.com', $emails);
    }

    // =========================================================================
    // GET /api/invitations/sent
    // =========================================================================

    public function testGetSentInvitationsReturnsArray(): void
    {
        $client  = $this->createAuthenticatedClient();
        $friend  = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addSentInvitation($friend);
        $em->flush();

        $client->request('GET', '/api/invitations/sent');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data  = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $emails = array_column($data, 'email');
        $this->assertContains('phpunit2@openhub.com', $emails);
    }

    // =========================================================================
    // POST /api/invitations/accept/{senderId}
    // =========================================================================

    public function testAcceptInvitationReturns404WhenSenderNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('POST', '/api/invitations/accept/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testAcceptInvitationSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $sender = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $sender->addSentInvitation($user);
        $em->flush();

        $client->request('POST', '/api/invitations/accept/' . $sender->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertStringContainsString('amis', $data['message']);
    }

    // =========================================================================
    // GET /api/user/friends
    // =========================================================================

    public function testGetUserFriendsReturnsArray(): void
    {
        $client = $this->createAuthenticatedClient();
        $friend = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addFriend($friend);
        $em->flush();

        $client->request('GET', '/api/user/friends');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data  = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $emails = array_column($data, 'email');
        $this->assertContains('phpunit2@openhub.com', $emails);
    }

    // =========================================================================
    // DELETE /api/delete/friends/{id}
    // =========================================================================

    public function testDeleteFriendReturns404WhenFriendNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', '/api/delete/friends/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Ami introuvable', $data['message']);
    }

    public function testDeleteFriendSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $friend = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addFriend($friend);
        $friend->addFriend($user);
        $em->flush();

        $client->request('DELETE', '/api/delete/friends/' . $friend->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Ami supprimé avec succès', $data['message']);
    }

    // =========================================================================
    // DELETE /api/invitations/delete-received/{senderId}
    // =========================================================================

    public function testDeleteReceivedInvitationSuccess(): void
    {
        $client = $this->createAuthenticatedClient();
        $sender = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $sender->addSentInvitation($user);
        $em->flush();

        $client->request('DELETE', '/api/invitations/delete-received/' . $sender->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Invitation reçue supprimée avec succès', $data['message']);
    }

    // =========================================================================
    // DELETE /api/invitations/delete-sent/{receiverId}
    // =========================================================================

    public function testDeleteSentInvitationSuccess(): void
    {
        $client   = $this->createAuthenticatedClient();
        $receiver = $this->createSecondUser();

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addSentInvitation($receiver);
        $em->flush();

        $client->request('DELETE', '/api/invitations/delete-sent/' . $receiver->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Invitation envoyée supprimée avec succès', $data['message']);
    }
}