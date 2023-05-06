<?php

namespace App\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="survey_user")
 */
class User extends MyEntity
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     */
    private $id;

    //json array of preferences
    /**
     * @ORM\Column(type="text",nullable=true)
     */
    private ?string $preferences = null;

    /**
     * @ORM\Column(type="text",nullable=true)
     */
    private $name;

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set id.
     *
     * @param string $id
     *
     * @return User
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set preferences.
     *
     * @param array $preferences
     *
     * @return User
     */
    public function setPreferences($preferences)
    {
        $this->preferences = json_encode($preferences, JSON_UNESCAPED_UNICODE);

        return $this;
    }

    /**
     * add preference
     * @param string $key
     * @param string $value
     */
    public function addPreference($key, $value)
    {
        // Get the current preferences array
        $preferences = $this->getPreferences();

        // Add the new key-value pair
        $preferences[$key] = $value;

        // Set the updated preferences array
        $this->setPreferences($preferences);

        return $this;
    }

    /**
     * Get preferences.
     *
     * @return array
     */
    public function getPreferences()
    {
        return $this->preferences != null ? json_decode($this->preferences, true) : [];
    }
}
