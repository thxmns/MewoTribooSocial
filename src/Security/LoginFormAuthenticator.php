<?php
namespace App\Security;

use App\Entity\User;
use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Doctrine\ORM\EntityManagerInterface;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private UrlGeneratorInterface $urlGenerator;
    private EntityManagerInterface $entityManager;

    public function __construct(UrlGeneratorInterface $urlGenerator, EntityManagerInterface $entityManager)
    {
        $this->urlGenerator = $urlGenerator;
        $this->entityManager = $entityManager;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $provided2FACode = $request->request->get('_2fa_code', ''); // Récupère le code 2FA soumis
    
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);
    
        // Rechercher l'utilisateur par l'email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
    
        if ($user && $user->is2FAEnabled()) {
            // Si l'utilisateur a activé la 2FA, on doit vérifier le code 2FA
            $googleAuthenticator = new GoogleAuthenticator();
            $isValid2FA = $googleAuthenticator->checkCode($user->getGoogleAuthenticatorSecret(), $provided2FACode);
    
            if (!$isValid2FA) {
                // Si le code 2FA est incorrect, lever une exception d'authentification
                throw new AuthenticationException('Invalid 2FA code. Please try again.');
            }
        }
    
        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }
    
    

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /** @var User $user */
        $user = $token->getUser();

        // Si l'utilisateur a activé Google Authenticator, demander le code 2FA
        if ($user->getGoogleAuthenticatorSecret()) {
            // Enregistre dans la session que l'authentification 2FA est requise
            $request->getSession()->set('2fa_required', true);
            $request->getSession()->set('user_2fa_id', $user->getId());

            return new RedirectResponse($this->urlGenerator->generate('app_home'));
        }

    

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
{
    // Ajouter un message d'erreur flash pour informer l'utilisateur
    $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

    // Rediriger vers la page de login
    return new RedirectResponse($this->getLoginUrl($request));
}

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    public function check2FACode(Request $request): bool
    {
        // Récupère l'utilisateur à partir de la session
        $userId = $request->getSession()->get('user_2fa_id');
        if (!$userId) {
            return false;
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user || !$user->getGoogleAuthenticatorSecret()) {
            return false;
        }

        // Récupère le code soumis
        $code = $request->request->get('_2fa_code'); // Code 2FA soumis par l'utilisateur

        // Vérifie le code 2FA avec Google Authenticator
        $googleAuthenticator = new GoogleAuthenticator();
        return $googleAuthenticator->checkCode($user->getGoogleAuthenticatorSecret(), $code); // Validation du code
    }
}
