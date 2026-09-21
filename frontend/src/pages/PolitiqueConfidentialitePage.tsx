import { Link } from 'react-router-dom'

/** Page de la politique de confidentialité (RGPD). */
export function PolitiqueConfidentialitePage() {
  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="mb-6 text-2xl font-bold text-gray-900">Politique de Confidentialite</h1>

      <div className="space-y-6 rounded-2xl bg-white p-8 shadow-sm text-sm text-gray-700 leading-relaxed">
        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Introduction</h2>
          <p>
            La presente politique de confidentialite decrit comment Transport ANCF collecte,
            utilise et protege vos donnees personnelles conformement au
            Reglement General sur la Protection des Donnees (RGPD).
          </p>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Donnees collectees</h2>
          <p>Nous collectons les donnees suivantes :</p>
          <ul className="mt-2 list-disc pl-5 space-y-1">
            <li><strong>Donnees d'inscription :</strong> nom, prenom, adresse email, mot de passe (chiffre)</li>
            <li><strong>Donnees de geolocalisation :</strong> position GPS (uniquement avec votre consentement via le navigateur)</li>
            <li><strong>Historique de recherche :</strong> termes de recherche d'arrets et stations</li>
            <li><strong>Arrets favoris :</strong> liste des arrets enregistres</li>
          </ul>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Finalites du traitement</h2>
          <ul className="list-disc pl-5 space-y-1">
            <li>Authentification et gestion de votre compte utilisateur</li>
            <li>Affichage des arrets et horaires a proximite de votre position</li>
            <li>Sauvegarde de vos arrets favoris</li>
            <li>Amelioration du service via l'analyse anonyme des recherches</li>
            <li>Supervision technique (logs API pour la performance et les erreurs)</li>
          </ul>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Base legale</h2>
          <ul className="list-disc pl-5 space-y-1">
            <li><strong>Consentement :</strong> geolocalisation (demande explicite du navigateur)</li>
            <li><strong>Execution du contrat :</strong> gestion du compte, favoris, recherches</li>
            <li><strong>Interet legitime :</strong> supervision technique et securite du service</li>
          </ul>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Duree de conservation</h2>
          <ul className="list-disc pl-5 space-y-1">
            <li><strong>Compte utilisateur :</strong> conserve tant que le compte est actif</li>
            <li><strong>Historique de recherche :</strong> 12 mois</li>
            <li><strong>Logs API :</strong> 6 mois</li>
            <li><strong>Donnees de geolocalisation :</strong> non stockees (usage en temps reel uniquement)</li>
          </ul>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Securite des donnees</h2>
          <ul className="list-disc pl-5 space-y-1">
            <li>Mots de passe chiffres avec bcrypt</li>
            <li>Authentification par jetons JWT avec expiration</li>
            <li>Communications chiffrees (HTTPS en production)</li>
            <li>Limitation du taux de requetes (rate limiting) sur les endpoints sensibles</li>
            <li>Protection CORS restreinte aux origines autorisees</li>
          </ul>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Vos droits (RGPD)</h2>
          <p>Conformement au RGPD, vous disposez des droits suivants :</p>
          <ul className="mt-2 list-disc pl-5 space-y-1">
            <li><strong>Droit d'acces :</strong> consulter vos donnees via la page Profil</li>
            <li><strong>Droit de rectification :</strong> modifier vos informations dans les parametres</li>
            <li><strong>Droit a l'effacement :</strong> supprimer votre compte depuis la page Profil (suppression definitive de toutes vos donnees)</li>
            <li><strong>Droit a la portabilite :</strong> export de vos donnees sur demande</li>
            <li><strong>Droit d'opposition :</strong> vous pouvez desactiver la geolocalisation a tout moment</li>
          </ul>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Partage des donnees</h2>
          <p>
            Vos donnees personnelles ne sont ni vendues ni partagees avec des tiers.
            Seules les donnees techniques strictement necessaires sont echangees
            avec l'API Ile-de-France Mobilites pour fournir les informations de transport.
          </p>
        </section>

        <section>
          <h2 className="mb-2 text-lg font-semibold text-gray-900">Contact</h2>
          <p>
            Pour exercer vos droits ou pour toute question relative a la protection
            de vos donnees, contactez-nous a :{' '}
            <strong>al.rodrigues@ecole-ipssi.net</strong>
          </p>
        </section>

        <div className="border-t border-gray-200 pt-4 text-xs text-gray-500">
          <p>Derniere mise a jour : mars 2026</p>
          <p className="mt-1">
            <Link to="/mentions-legales" className="text-blue-700 hover:underline">
              Mentions legales
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
