<?php

namespace App\Tests\Entity;

use App\Entity\Invitations;
use PHPUnit\Framework\TestCase;

class InvitationsEntityTest extends TestCase
{
    private Invitations $invitations;

    protected function setUp(): void
    {
        $this->invitations = new Invitations();
    }

    public function testEntityCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Invitations::class, $this->invitations);
    }

    public function testIdIsInitiallyNull(): void
    {
        $this->assertNull($this->invitations->getId());
    }

    public function testIdCanBeSetViaReflection(): void
    {
        // L'ID est généralement défini par Doctrine via réflexion
        $reflection = new \ReflectionClass($this->invitations);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->invitations, 123);

        $this->assertEquals(123, $this->invitations->getId());
    }

    public function testIdIsIntegerWhenSet(): void
    {
        $reflection = new \ReflectionClass($this->invitations);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->invitations, 42);

        $this->assertIsInt($this->invitations->getId());
    }

    public function testMultipleInstancesHaveDifferentIds(): void
    {
        $invitation1 = new Invitations();
        $invitation2 = new Invitations();

        $reflection = new \ReflectionClass(Invitations::class);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        
        $property->setValue($invitation1, 1);
        $property->setValue($invitation2, 2);

        $this->assertEquals(1, $invitation1->getId());
        $this->assertEquals(2, $invitation2->getId());
        $this->assertNotEquals($invitation1->getId(), $invitation2->getId());
    }

    public function testIdCannotBeSetDirectly(): void
    {
        // Vérifie qu'il n'y a pas de méthode setId
        $this->assertFalse(method_exists($this->invitations, 'setId'));
    }

    public function testEntityHasOnlyExpectedMethods(): void
    {
        $methods = get_class_methods($this->invitations);
        
        // Sans constructeur explicite, seule la méthode getId devrait être visible
        // Les méthodes magiques comme __construct n'apparaissent pas dans get_class_methods
        $this->assertTrue(in_array('getId', $methods), "Method getId should exist");
        $this->assertCount(1, $methods, "Entity should only have the getId method");
    }

    /**
     * Teste que l'entité peut être utilisée avec Doctrine
     */
    public function testEntityIsCompatibleWithDoctrine(): void
    {
        // Vérifie que l'entité a les annotations/attributs nécessaires
        $reflection = new \ReflectionClass($this->invitations);
        
        // Vérifie que la propriété id existe
        $this->assertTrue($reflection->hasProperty('id'));
        
        $idProperty = $reflection->getProperty('id');
        
        // Vérifie que la propriété id a l'attribut Column
        $attributes = $idProperty->getAttributes();
        $hasColumnAttribute = false;
        
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Doctrine\\ORM\\Mapping\\Column') {
                $hasColumnAttribute = true;
                break;
            }
        }
        
        $this->assertTrue($hasColumnAttribute, "id property should have Column attribute");
    }
}