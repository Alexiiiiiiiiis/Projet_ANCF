<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Adaptateur du cache applicatif : Redis partout où un REDIS_URL est déclaré (stack Docker de
 * développement, docker-compose.prod.yml, PaaS), système de fichiers sinon — l'hébergement
 * mutualisé AlwaysData n'en propose pas, et l'application doit y tourner sans modification.
 *
 * Pourquoi en PHP et pas en YAML : « app » attend une référence de service, résolue à la
 * compilation du conteneur, alors qu'une variable %env()% n'est lue qu'à l'exécution — Symfony
 * refuse le mélange (« Incompatible use of dynamic environment variables »). Le choix est donc
 * fait ici, pendant la compilation ; le cache étant réchauffé au démarrage du conteneur, les
 * variables de l'hébergeur sont déjà en place à ce moment-là.
 *
 * Pourquoi dans packages/ et non plus dans packages/prod/ : en dev la stack Docker fournit un
 * Redis, qui restait inutilisé tant que ce choix ne valait qu'en production — le conteneur
 * tournait sans que rien ne s'y connecte. Le Kernel charge packages/*.php avant packages/*.yaml,
 * donc cache.yaml ne doit plus déclarer « app » sous peine d'écraser ce réglage.
 *
 * Les tests ne dépendent d'aucun service externe : .env.test laisse REDIS_URL vide.
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
