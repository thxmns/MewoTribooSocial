<?php
namespace App\Controller;

use App\Security\LoginFormAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;
use App\Service\MailHogService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;







class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, LoginFormAuthenticator $authenticator, EntityManagerInterface $entityManager): Response
    {
        // Récupère les dernières erreurs de connexion
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();
    
        // Si le formulaire est soumis
        if ($request->isMethod('POST')) {
            // Récupérer les informations du formulaire
            $email = $request->request->get('email');
            $password = $request->request->get('password');
    
            // Rechercher l'utilisateur par l'email
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    
            if ($user) {
                // Vérifier le mot de passe
                $isPasswordValid = $this->passwordHasher->isPasswordValid($user, $password);
    
                if (!$isPasswordValid) {
                    $error = 'Invalid credentials. Please try again.';
                    return $this->render('security/login.html.twig', [
                        'last_username' => $lastUsername,
                        'error' => $error
                    ]);
                }
    
                // Si tout est correct et pas de 2FA, rediriger vers la page d'accueil
                return $this->redirectToRoute('app_home');
            } else {
                // Si l'utilisateur n'est pas trouvé
                $error = 'User not found. Please try again.';
            }
        }
    
        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error ?: null
        ]);
    }




    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/2fa/qr-code/{userId}', name: 'app_2fa_qr_code')]
public function displayQrCode(int $userId, EntityManagerInterface $entityManager, LoggerInterface $logger): Response
{
    $user = $entityManager->getRepository(User::class)->find($userId);

    if (!$user || !$user->getGoogleAuthenticatorSecret()) {
        $logger->error('User or 2FA configuration not found for userId: ' . $userId);
        throw $this->createNotFoundException('User or 2FA configuration not found');
    }

    // Si la 2FA est déjà activée, rediriger vers une autre page
    if ($user->is2FAEnabled()) {
        $this->addFlash('error', 'You have already activated 2FA.');
        return $this->redirectToRoute('app_home'); // Rediriger vers la page d'accueil ou une autre page
    }

    // Générer l'URL du QR code
    $qrCodeUrl = GoogleQrUrl::generate($user->getEmail(), $user->getGoogleAuthenticatorSecret(), 'Triboo');

    // Générer un code de récupération unique
    $recoveryCode = bin2hex(random_bytes(5));
    $user->setRecoveryCode($recoveryCode);
    $entityManager->flush();

    return $this->render('registration/qr_code.html.twig', [
        'qrCodeUrl' => $qrCodeUrl,
        'recoveryCode' => $recoveryCode,
        'userId' => $userId, // Passe l'ID de l'utilisateur au template
    ]);
    
}


#[Route('/2fa/activate/{userId}', name: 'app_2fa_activate')]
public function activate2FA(int $userId, EntityManagerInterface $entityManager): Response
{
    $user = $entityManager->getRepository(User::class)->find($userId);

    if (!$user) {
        throw $this->createNotFoundException('User not found');
    }

    // Activer la 2FA
    $user->setIs2FAEnabled(true);
    $entityManager->flush();

    // Rediriger vers la page de connexion ou une autre page
    return $this->redirectToRoute('app_login');
}


#[Route('/recovery-a2f', name: 'app_2fa_recovery')]
public function recoveryA2F(Request $request, EntityManagerInterface $entityManager): Response
{
    $error = null;

    if ($request->isMethod('POST')) {
        $recoveryCode = $request->request->get('recovery_code');

        // Rechercher l'utilisateur par le recovery code
        $user = $entityManager->getRepository(User::class)->findOneBy(['recoveryCode' => $recoveryCode]);

        if ($user) {
            // Réafficher le QR code
            $qrCodeUrl = GoogleQrUrl::generate($user->getEmail(), $user->getGoogleAuthenticatorSecret(), 'Triboo');

            return $this->render('registration/qr_code.html.twig', [
                'qrCodeUrl' => $qrCodeUrl,
                'recoveryCode' => $user->getRecoveryCode(),
                'userId' => $user->getId(),
            ]);
        } else {
            $error = 'Invalid recovery code. Please try again.';
        }
    }

    return $this->render('registration/recovery_code.html.twig', [
        'error' => $error,
    ]);
}

#[Route('/2fa/code', name: 'app_2fa_code')]
public function enter2FACode(Request $request, LoginFormAuthenticator $authenticator): Response
{
    $error = null;

    if ($request->isMethod('POST')) {
        // Vérifier le code 2FA soumis
        $isValid2FA = $authenticator->check2FACode($request);

        if ($isValid2FA) {
            // Supprimer la session 2FA et rediriger vers la page d'accueil
            $request->getSession()->remove('2fa_required');
            $request->getSession()->remove('user_2fa_id');

            return $this->redirectToRoute('app_home');
        } else {
            $error = 'Invalid 2FA code. Please try again.';
        }
    }

    return $this->render('security/2fa_code.html.twig', [
        'error' => $error,
    ]);
}

#[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager, MailHogService $mailHogService): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                // Générer un jeton de réinitialisation
                $resetToken = bin2hex(random_bytes(32));
                $user->setResetToken($resetToken);
                $entityManager->flush();

                // Générer l'URL de réinitialisation
                $resetUrl = $this->generateUrl('app_reset_password', ['token' => $resetToken], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

                // Envoyer l'e-mail de réinitialisation via MailHogService
                $mailHogService->sendTestEmail($user->getEmail(), 'Password Reset Request', "<a href=\"$resetUrl\">Reset your password</a>");

                $this->addFlash('success', 'A password reset email has been sent to your inbox.');
                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('error', 'No account found for that email.');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(Request $request, string $token, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Invalid token');
        }

        if ($request->isMethod('POST')) {
            // Récupérer et hacher le nouveau mot de passe
            $newPassword = $request->request->get('password');
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            // Réinitialiser le jeton après utilisation
            $user->setResetToken(null);
            $entityManager->flush();

            // Rediriger l'utilisateur avec un message de succès
            $this->addFlash('success', 'Your password has been reset successfully.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }




    

}
