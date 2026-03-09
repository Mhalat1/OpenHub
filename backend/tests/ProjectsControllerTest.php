<?php

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProjectsControllerTest extends WebTestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Génère un JWT en appelant directement /api/login_check,
     * après s'être assuré que l'utilisateur existe en BDD test
     * avec le bon mot de passe (on le recrée proprement à chaque fois).
     */
    private function createAuthenticatedClient(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client  = static::createClient();
        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $hasher  = static::getContainer()->get(UserPasswordHasherInterface::class);

        $email    = 'phpunit@openhub.com';
        $password = 'TestPass!123';

        // Supprimer l'utilisateur existant pour éviter les conflits de hash
        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            // Détacher les projets pour éviter les contraintes FK
            foreach ($existing->getProject() as $p) {
                $existing->removeProject($p);
            }
            $em->remove($existing);
            $em->flush();
            $em->clear();
        }

        // Créer un utilisateur frais avec un hash valide
        $user = new User();
        $user->setEmail($email);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em->persist($user);
        $em->flush();

        // Obtenir le JWT via /api/login_check
        $client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );

        $response = json_decode($client->getResponse()->getContent(), true);

        // Compatibilité : certaines versions de LexikJWT retournent 'token', d'autres 'access_token'
        $token = $response['token'] ?? $response['access_token'] ?? null;

        if (empty($token)) {
            throw new \RuntimeException(
                sprintf(
                    "Impossible d'obtenir un JWT.\nStatus: %d\nRéponse: %s\n\n" .
                    "→ Vérifiez que /api/login_check est bien configuré pour l'env 'test'\n" .
                    "→ Vérifiez que JWT_SECRET_KEY est défini dans .env.test ou .env",
                    $client->getResponse()->getStatusCode(),
                    $client->getResponse()->getContent()
                )
            );
        }

        $client->setServerParameter('HTTP_Authorization', 'Bearer ' . $token);

        return $client;
    }

    /**
     * Retourne l'utilisateur de test depuis la BDD.
     */
    private function getTestUser(): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        return $em->getRepository(User::class)->findOneBy(['email' => 'phpunit@openhub.com']);
    }

    /**
     * Crée et persiste un Project en BDD de test.
     */
    private function createProject(
        string $name        = 'Refonte site web',
        string $description = 'Refonte complète du site vitrine',
        array  $skills      = ['PHP', 'Symfony', 'React'],
        string $startDate   = '2024-03-01',
        string $endDate     = '2024-09-30'
    ): Project {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $project = new Project();
        $project->setName($name);
        $project->setDescription($description);
        // setRequiredSkills attend une string (stockée en JSON dans la BDD)
        $project->setRequiredSkills(json_encode($skills));
        $project->setStartDate(new \DateTimeImmutable($startDate));
        $project->setEndDate(new \DateTimeImmutable($endDate));

        $em->persist($project);
        $em->flush();

        return $project;
    }

    // =========================================================================
    // GET /api/allprojects
    // =========================================================================

    public function testProjectsReturnsAllProjects(): void
    {
        $client = $this->createAuthenticatedClient();

        $this->createProject('Refonte site web');
        $this->createProject('Application mobile RH', 'App RH interne', ['Flutter', 'Dart']);

        $client->request('GET', '/api/allprojects');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(2, count($data));

        $first = $data[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('requiredSkills', $first);
        $this->assertArrayHasKey('startDate', $first);
        $this->assertArrayHasKey('endDate', $first);
    }

    public function testProjectsReturnsEmptyArrayWhenNoProjects(): void
    {
        $client = $this->createAuthenticatedClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($em->getRepository(Project::class)->findAll() as $p) {
            $em->remove($p);
        }
        $em->flush();

        $client->request('GET', '/api/allprojects');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame([], $data);
    }

    // =========================================================================
    // GET /api/user/projects
    // =========================================================================

    public function testUserProjectsReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/user/projects');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUserProjectsReturnsProjectsForAuthenticatedUser(): void
    {
        $client  = $this->createAuthenticatedClient();
        $project = $this->createProject('Refonte site web');

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addProject($project);
        $em->flush();

        $client->request('GET', '/api/user/projects');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertContains('Refonte site web', array_column($data, 'name'));
    }

    // =========================================================================
    // POST /api/user/add/project
    // =========================================================================

    public function testAddProjectReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();

        $client->request(
            'POST', '/api/user/add/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['project_id' => 1])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testAddProjectReturns400WhenMissingProjectId(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/user/add/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Missing project_id', $data['message']);
    }

    public function testAddProjectReturns404WhenProjectNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/user/add/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['project_id' => 999999])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Project not found', $data['message']);
    }

    public function testAddProjectReturns400WhenAlreadyAdded(): void
    {
        $client  = $this->createAuthenticatedClient();
        $project = $this->createProject('Projet déjà ajouté');

        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getTestUser();
        $user->addProject($project);
        $em->flush();

        $client->request(
            'POST', '/api/user/add/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['project_id' => $project->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Project already added to user', $data['message']);
    }

    public function testAddProjectSuccess(): void
    {
        $client  = $this->createAuthenticatedClient();
        $project = $this->createProject('Nouveau projet');

        $client->request(
            'POST', '/api/user/add/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['project_id' => $project->getId()])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Nouveau projet', $data['project_name']);
    }

    // =========================================================================
    // POST /api/create/new/project
    // =========================================================================

    public function testCreateProjectReturns400WhenMissingFields(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/create/new/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Projet incomplet'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Missing required fields', $data['message']);
    }

    public function testCreateProjectReturns400WhenInvalidDate(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/create/new/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name'           => 'Refonte site web',
                'description'    => 'Description du projet',
                'requiredSkills' => '["PHP","Symfony"]',
                'startDate'      => 'date-invalide',
                'endDate'        => '2024-09-30',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Invalid date format', $data['message']);
    }

    public function testCreateProjectSuccess(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'POST', '/api/create/new/project', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name'           => 'Refonte site web',
                'description'    => 'Refonte complète du site vitrine',
                'requiredSkills' => '["PHP","Symfony","React"]',
                'startDate'      => '2024-03-01',
                'endDate'        => '2024-09-30',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Project created successfully', $data['message']);
        $this->assertArrayHasKey('project_id', $data);
        $this->assertIsInt($data['project_id']);
    }

    // =========================================================================
    // PUT/PATCH /api/modify/project/{id}
    // =========================================================================

    public function testModifyProjectReturns404WhenNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request(
            'PUT', '/api/modify/project/999999', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Nouveau nom'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Project not found', $data['message']);
    }

    public function testModifyProjectSuccess(): void
    {
        $client  = $this->createAuthenticatedClient();
        $project = $this->createProject('Refonte site web');

        $client->request(
            'PUT', '/api/modify/project/' . $project->getId(), [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'name'        => 'Refonte site web v2',
                'description' => 'Nouvelle description',
                'endDate'     => '2025-03-31',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Project updated successfully', $data['message']);
        $this->assertSame($project->getId(), $data['project_id']);
    }

    public function testModifyProjectReturns400OnInvalidEndDate(): void
    {
        $client  = $this->createAuthenticatedClient();
        $project = $this->createProject();

        $client->request(
            'PATCH', '/api/modify/project/' . $project->getId(), [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['endDate' => 'date-invalide'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Invalid endDate format', $data['message']);
    }

    // =========================================================================
    // DELETE /api/delete/project/{id}
    // =========================================================================

    public function testDeleteProjectReturns404WhenNotFound(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('DELETE', '/api/delete/project/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Project not found', $data['message']);
    }

    public function testDeleteProjectSuccess(): void
    {
        $client  = $this->createAuthenticatedClient();
        $project = $this->createProject('Projet à supprimer');

        $client->request('DELETE', '/api/delete/project/' . $project->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Project deleted successfully', $data['message']);

        $em      = static::getContainer()->get(EntityManagerInterface::class);
        $deleted = $em->getRepository(Project::class)->find($project->getId());
        $this->assertNull($deleted);
    }
}