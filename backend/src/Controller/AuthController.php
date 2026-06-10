<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepo,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly MailerInterface $mailer,
    ) {}

    #[Route('/login_check', name: 'api_login_check', methods: ['POST'])]
    public function loginCheck(): never
    {
        // Intercepté par le firewall Symfony — ce code n'est jamais exécuté
        throw new \LogicException('Ce endpoint est géré par le security firewall.');
    }

    #[Route('/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $email     = trim($data['email'] ?? '');
        $password  = $data['password'] ?? '';
        $firstName = trim($data['firstName'] ?? '');
        $lastName  = trim($data['lastName'] ?? '');

        if (strlen($password) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepo->findOneBy(['email' => $email])) {
            return $this->json(['error' => 'Cet email est déjà utilisé.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email)
             ->setFirstName($firstName)
             ->setLastName($lastName)
             ->setRoles(['ROLE_USER'])
             ->setPassword($this->passwordHasher->hashPassword($user, $password));

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($user);
        $this->em->flush();

        return $this->json($user->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'auth_me', methods: ['GET'])]
    public function me(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($user->toArray());
    }

    #[Route('/me', name: 'auth_update_profile', methods: ['PUT'])]
    public function updateProfile(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['firstName'])) {
            $user->setFirstName(trim($data['firstName']));
        }
        if (isset($data['lastName'])) {
            $user->setLastName(trim($data['lastName']));
        }
        if (isset($data['email'])) {
            $newEmail = trim($data['email']);
            if ($newEmail !== $user->getEmail()) {
                if ($this->userRepo->findOneBy(['email' => $newEmail])) {
                    return $this->json(['error' => 'Cet email est déjà utilisé.'], Response::HTTP_CONFLICT);
                }
                $user->setEmail($newEmail);
            }
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        return $this->json($user->toArray());
    }

    #[Route('/change-password', name: 'auth_change_password', methods: ['PUT'])]
    public function changePassword(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            return $this->json(['error' => 'Mot de passe actuel incorrect.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($newPassword) < 8) {
            return $this->json(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $this->em->flush();

        return $this->json(['message' => 'Mot de passe modifié avec succès.']);
    }

    #[Route('/account', name: 'auth_delete_account', methods: ['DELETE'])]
    public function deleteAccount(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $this->json(['message' => 'Compte supprimé conformément au RGPD.'], Response::HTTP_OK);
    }

    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->json(['error' => 'Email requis.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepo->findOneBy(['email' => $email]);

        // Toujours renvoyer 200 pour ne pas exposer si l'email existe
        if (!$user) {
            return $this->json(['message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.']);
        }

        $token = bin2hex(random_bytes(32));
        $user->setResetPasswordToken($token)
             ->setResetPasswordExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->em->flush();

        // Envoi de l'email de réinitialisation
        $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:5173';
        $resetLink = $frontendUrl . '/reinitialiser-mot-de-passe?token=' . $token;

        $emailMessage = (new Email())
            ->from('noreply@ancf-transport.fr')
            ->to($user->getEmail())
            ->subject('ANCF Transport — Réinitialisation de votre mot de passe')
            ->html(
                '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">'
                . '<h2 style="color: #1e40af;">Réinitialisation de mot de passe</h2>'
                . '<p>Bonjour ' . htmlspecialchars($user->getFirstName()) . ',</p>'
                . '<p>Vous avez demandé la réinitialisation de votre mot de passe sur ANCF Transport.</p>'
                . '<p>Cliquez sur le bouton ci-dessous pour définir un nouveau mot de passe :</p>'
                . '<p style="text-align: center; margin: 30px 0;">'
                . '<a href="' . $resetLink . '" style="background-color: #1e40af; color: white; padding: 12px 24px; '
                . 'text-decoration: none; border-radius: 6px; font-weight: bold;">Réinitialiser mon mot de passe</a>'
                . '</p>'
                . '<p style="color: #6b7280; font-size: 14px;">Ce lien expire dans 1 heure.</p>'
                . '<p style="color: #6b7280; font-size: 14px;">Si vous n\'avez pas fait cette demande, ignorez cet email.</p>'
                . '<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">'
                . '<p style="color: #9ca3af; font-size: 12px;">ANCF Transport — Système d\'information temps réel</p>'
                . '</div>'
            );

        try {
            $this->mailer->send($emailMessage);
        } catch (\Throwable) {
            // Log l'erreur mais ne pas exposer le détail à l'utilisateur
        }

        $response = ['message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'];

        // En dev uniquement : retourner le token pour faciliter les tests
        if ($_ENV['APP_ENV'] === 'dev') {
            $response['debug_token'] = $token;
            $response['debug_reset_link'] = $resetLink;
        }

        return $this->json($response);
    }

    #[Route('/reset-password', name: 'auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data        = json_decode($request->getContent(), true);
        $token       = trim($data['token'] ?? '');
        $newPassword = $data['newPassword'] ?? '';

        if (!$token || !$newPassword) {
            return $this->json(['error' => 'Token et nouveau mot de passe requis.'], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($newPassword) < 8) {
            return $this->json(['error' => 'Le mot de passe doit contenir au moins 8 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepo->findOneBy(['resetPasswordToken' => $token]);

        if (!$user || $user->getResetPasswordExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['error' => 'Token invalide ou expiré.'], Response::HTTP_BAD_REQUEST);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword))
             ->setResetPasswordToken(null)
             ->setResetPasswordExpiresAt(null);

        $this->em->flush();

        return $this->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }
}
