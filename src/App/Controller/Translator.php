<?php

namespace App\Controllers;

use App\Models\Translation;

class Translator
{
    private $locale;
    private $entityManager;
    private $translations;

    public function __construct(?string $locale)
    {
        $this->locale = $locale ?? 'en';
        $this->entityManager = DbContext::get_entity_manager();
        $this->translations = $this->entityManager->getRepository("App\Models\Translation")->findAll();
    }

    public function translate(string $key): ?string
    {
        // Iterate over translations to find matching key and language
        foreach ($this->translations as $translation) {
            if ($translation->getKey() === $key && $translation->getLanguage() === $this->locale) {
                return $translation->getTranslation();
            }
        }

        return $key;
    }

    /**
     * Add translation for a given text key and language
     *
     * @param string $key
     * @param string $language
     * @param string $text
     */
    public function addTranslation(string $key, string $language, string $text): void
    {
        // Create new translation entity
        $translation = new Translation();
        $translation->setKey($key);
        $translation->setLanguage($language);
        $translation->setTranslation($text);

        // Persist translation
        $this->entityManager->persist($translation);
        $this->entityManager->flush();
    }

    /**
     * Initializes translations for select_registration and survey_not_available
     */
    public function initialize(): void
    {
        $this->addTranslation('select_registration', 'en', 'Select option');
        $this->addTranslation('select_registration', 'am', 'አማራጭ ይምረጡ');

        $this->addTranslation('survey_not_available', 'en', 'Survey not currently available');
        $this->addTranslation('survey_not_available', 'am', 'ይዘት በአሁኑ ጊዜ አይገኝም');

        $this->addTranslation('share_contact', 'en', 'Share Contact');
        $this->addTranslation('share_contact', 'am', 'መለያ አጋራ');

        $this->addTranslation('For employers', 'am', 'ለሰራተኛ ፈላጊዎች');
        $this->addTranslation('For job seekers', 'am', 'ለስራ ፈላጊዎች');
    }
}
