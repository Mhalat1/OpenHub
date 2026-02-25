<?php

namespace App\Service;
use App\Repository\UserRepository;

class UserService
{
public function __construct(private readonly UserRepository $userRepository)
{
}

public function findAll()
{
    return $this->userRepository->findby([], ['firstName' => 'ASC']);

}
}