<?php

namespace App\DataFixtures;

use App\Entity\SystemParameter;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /** Remplit la base de démo : un admin, un utilisateur et les paramètres système. */
    public function load(ObjectManager $manager): void
    {
        $this->createUser(
            manager: $manager,
            email: 'admin@ancf.fr',
            plainPassword: 'Admin1234!',
            firstName: 'Admin',
            lastName: 'ANCF',
            roles: ['ROLE_ADMIN', 'ROLE_USER']
        );

        $this->createUser(
            manager: $manager,
            email: 'user@ancf.fr',
            plainPassword: 'User1234!',
            firstName: 'Jean',
            lastName: 'Dupont',
            roles: ['ROLE_USER']
        );

        $this->loadSystemParameters($manager);

        $manager->flush();
    }

    /** Crée les paramètres système par défaut (rafraîchissement, rayon, cache...). */
    private function loadSystemParameters(ObjectManager $manager): void
    {
        $defaults = [
            ['refresh_interval', '30', 'Intervalle de rafraîchissement', 'Intervalle (en secondes) pour le rafraîchissement automatique des départs', 'number'],
            ['default_radius', '500', 'Rayon par défaut', 'Rayon de recherche (en mètres) pour les arrêts à proximité', 'number'],
            ['max_favorites', '20', 'Favoris maximum', 'Nombre maximum de favoris par utilisateur', 'number'],
            ['alerts_cache_ttl', '1800', 'Cache alertes (TTL)', 'Durée de vie du cache des alertes (en secondes)', 'number'],
            ['departures_cache_ttl', '30', 'Cache départs (TTL)', 'Durée de vie du cache des prochains départs (en secondes)', 'number'],
            ['maintenance_mode', 'false', 'Mode maintenance', 'Activer le mode maintenance (true/false)', 'boolean'],
        ];

        foreach ($defaults as [$key, $value, $label, $description, $type]) {
            $param = new SystemParameter();
            $param->setParamKey($key)
                  ->setParamValue($value)
                  ->setLabel($label)
                  ->setDescription($description)
                  ->setType($type);
            $manager->persist($param);
        }
    }

    /** Crée un utilisateur avec son mot de passe hashé. */
    private function createUser(
        ObjectManager $manager,
        string $email,
        string $plainPassword,
        string $firstName,
        string $lastName,
        array $roles,
    ): void {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles($roles);
        $user->setIsActive(true);

        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);

        $manager->persist($user);
    }
}
