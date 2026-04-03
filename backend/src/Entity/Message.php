<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(length: 25)]
    private string $content;

    #[ORM\ManyToOne(inversedBy: 'messages')] // ✅ corrigé: ce n'était pas 'createdat'
    #[ORM\JoinColumn(nullable: false)]
    private User $author;

    #[ORM\Column(length: 25)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private Conversation $conversation;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;
        return $this;
    }

    // ✅ Ajout : nom complet de l’auteur
    public function getAuthorName(): ?string
    {
        return $this->author->getFirstName() . ' ' . $this->author->getLastName();
    }

    // ✅ Ajout : titre de la conversation
    public function getConversationTitle(): ?string
    {
        return $this->conversation?->getTitle();
    }
}
