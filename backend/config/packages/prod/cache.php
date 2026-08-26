<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Adaptateur du cache applicatif en production : Redis là où l'hébergeur en fournit un
 * (Docker, PaaS), système de fichiers sinon (hébergement mutualisé, qui n'en propose pas).
 *
 * Pourquoi en PHP et pas en YAML : « app » attend une référence de service, résolue à la
 * compilation du conteneur, alors qu'une variable %env()% n'est lue qu'à l'exécution — Symfony
 * refuse le mélange (« Incompatible use of dynamic environment variables »). Le choix est donc
 * fait ici, pendant la compilation ; le cache étant réchauffé au démarrage du conteneur, les
 * variables de l'hébergeur sont déjà en place à ce moment-là.
 *
 * Pourquoi dans packages/prod/ et pas dans packages/ : le Kernel charge d'abord packages/*,
 * puis packages/{environnement}/*. Placé à la racine, ce fichier était lu avant cache.yaml,
 * qui écrasait ensuite son réglage.
 *
 * Le cache sur disque suffit à cette échelle : il ne coûte qu'un peu de latence sur le cache
 * de résultats Doctrine, et évite d'imposer un service Redis à l'hébergement.
 */
return static function (ContainerConfigurator $container): void {
    // getenv() en plus de $_ENV : selon le variables_order de PHP, les variables passées par
    // l'hébergeur n'atterrissent pas toujours dans $_ENV.
    $redis = (string) ($_ENV['REDIS_URL'] ?? $_SERVER['REDIS_URL'] ?? (getenv('REDIS_URL') ?: ''));

    $container->extension('framework', [
        'cache' => '' !== $redis
            ? ['app' => 'cache.adapter.redis', 'default_redis_provider' => $redis]
            : ['app' => 'cache.adapter.filesystem'],
    ]);
};
