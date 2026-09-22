<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SystemParameterRepository;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Accès typé aux paramètres réglables depuis l'espace d'administration (table system_parameter).
 *
 * Les valeurs y sont stockées en chaînes de caractères : ce service les convertit, les borne, et
 * fournit un repli quand la ligne est absente — une base fraîchement migrée, sans fixtures, doit
 * démarrer sans erreur plutôt que de se retrouver avec un TTL de 0 ou un rayon nul.
 *
 * Tout est chargé en une fois puis mis en cache : sans ça, chaque paramètre consulté au fil d'une
 * requête coûterait un SELECT, y compris sur les routes publiques les plus appelées.
 * L'administration invalide ce cache à chaque écriture (cf. AdminController::updateParameter),
 * pour qu'une modification prenne effet immédiatement au lieu d'attendre l'expiration du TTL.
 */
final class SystemParameters
{
    private const CACHE_KEY = 'system_parameters';
    private const CACHE_TTL = 60;

    /** @var array<string, string>|null mémorisation le temps de la requête courante */
    private ?array $values = null;

    public function __construct(
        private readonly SystemParameterRepository $repository,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Valeur entière d'un paramètre, ramenée dans [$min, $max].
     *
     * Le bornage n'est pas cosmétique : l'administration accepte n'importe quel nombre, et un TTL
     * de cache à 0 ferait repartir chaque requête vers l'API IDFM — de quoi épuiser le quota
     * quotidien en quelques minutes.
     */
    public function getInt(string $key, int $default, int $min = 1, int $max = \PHP_INT_MAX): int
    {
        $raw = $this->all()[$key] ?? null;

        if (null === $raw || '' === $raw || !is_numeric($raw)) {
            return $default;
        }

        return max($min, min($max, (int) $raw));
    }

    /** Valeur booléenne d'un paramètre ; $default si la ligne est absente ou illisible. */
    public function getBool(string $key, bool $default): bool
    {
        return match ($this->all()[$key] ?? null) {
            'true' => true,
            'false' => false,
            default => $default,
        };
    }

    /** À appeler après toute écriture en base, sinon le changement attendrait l'expiration du cache. */
    public function invalidate(): void
    {
        $this->values = null;

        try {
            $this->cache->deleteItem(self::CACHE_KEY);
        } catch (\Throwable) {
            // Un cache injoignable ne doit pas faire échouer l'enregistrement du paramètre.
        }
    }

    /** @return array<string, string> */
    private function all(): array
    {
        if (null !== $this->values) {
            return $this->values;
        }

        try {
            $item = $this->cache->getItem(self::CACHE_KEY);

            if ($item->isHit()) {
                return $this->values = $item->get();
            }

            $values = [];
            foreach ($this->repository->findAll() as $parameter) {
                $key = $parameter->getParamKey();
                if (null !== $key) {
                    $values[$key] = (string) $parameter->getParamValue();
                }
            }

            $item->set($values)->expiresAfter(self::CACHE_TTL);
            $this->cache->save($item);

            return $this->values = $values;
        } catch (\Throwable) {
            // Base injoignable ou table pas encore migrée : l'application continue de répondre
            // avec ses valeurs par défaut plutôt que de renvoyer une 500 sur toutes les routes.
            return $this->values = [];
        }
    }
}
