<?php

namespace App\Controllers;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


class EntityController
{
    protected $entityManager;
    protected $entityRepository;

    public function __construct($entity)
    {
        $this->entityManager = DbContext::get_entity_manager();
        $this->entityRepository = $this->entityManager->getRepository($entity);
    }

    /**
     * @return Entity[]
     */
    public function getAll()
    {
        $entities = $this->entityRepository->findBy(['deleted' => 0]);
        return $entities;
    }
    public function getById($id)
    {
        $entity = $this->entityRepository->find($id);
        return $entity;
    }

    /**
     * delete entity
     * @param $id
     */
    public function deleteEntity($id)
    {
            $entity = $this->entityRepository->find($id);
            // $entity->delete();
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            return $entity;
    }
}
