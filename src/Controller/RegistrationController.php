<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\UserAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\FormError;
use App\Service\JWTService;
use App\Service\SendMailService;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, 
            UserAuthenticatorInterface $userAuthenticator, UserAuthenticator $authenticator, 
            EntityManagerInterface $entityManager, SendMailService $email, JWTService $jwt): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        if($form->get('plainPassword')->get('second')->getData() != $form->get('plainPassword')->get('first')->getData())
        {
            $this->addFlash('danger', 'Confirmation erronée du mot de passe');
        }
        
        if ($form->isSubmitted() && $form->isValid()) {
            
            // encode the plain password
            $user->setPassword(
            $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();
            
            // email de vérification : on génère le jwt
            // on crée le header 
            $header = [
                'typ' => 'JWT',
                'alg' => 'HS256'
            ];
            
            $payload= [
                'user_id' => $user->getId()
            ];
            
            $token=$jwt->generate($header, $payload, $this->getParameter('app.jwtsecret'));
            //dd($email);
            // envoi de l'email
            $email->send(
                    "no-reply@myblog.org",
                    $user->getEmail(),
                    'Activation de votre compte myBlog',
                    'register',
                    compact('user', 'token')
            );
            //dd($token);
            return $userAuthenticator->authenticateUser(
                $user,
                $authenticator,
                $request
            );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
    
    #[Route('/verif/{token}', name: 'verif_user')]
    public function verifyUser($token, JWTService $jwt, UserRepository $userRepo, EntityManagerInterface $em)
    {
        if($jwt->isValid($token) && !$jwt->isExpired($token) && $jwt->check($token, $this->getParameter('app.jwtsecret')))
        {
            // on récupère le payload
            $payload=$jwt->getPayload($token);
            
            // on récupère le user du token
            $user=$userRepo->find($payload['user_id']);
            
            // on vérifie que l'utilisateur existe et n'a pas encore activé son compte
            if($user && !$user->getIsVerified())
            {
                $user->setIsVerified(true);
                $em->flush();
                $this->addFlash('success', 'Compte activé');
                
                return $this->redirectToRoute('app_main');
            }
        }
        
        $this->addFlash('danger', 'Le token est invalide ou a expiré');
        
        return $this->redirectToRoute('app_login');
    }
    
    #[Route('resendVerif', name: 'resend_verif')]
    public function resendVerif(UserRepository $userRepo, SendMailService $email, JWTService $jwt): Response
    {
        $user=$this->getUser();
        
        // on vérifie si l'utilisateur est connecté
        if(!$user) 
        {
            $this->addFlash('danger', 'Vous devez être connecté pour accéder à cette page');
            return $this->redirectToRoute('app_login');
        }

        // et si son compte n'est pas déjà activé
        if($user->getIsVerified())
        {
            $this->addFlash('warning', 'Cet utilisateur est déjà activé');
            return $this->redirectToRoute('app_main');
        }
        
        $header= [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];
        
        $payload= [
            'user_id' => $user->getId()  
        ];
        
        $token = $jwt->generate($header, $payload, $this->getParameter('app.jwtsecret'));
        
        $email->send('no-reply@myblog.org', 
                $user->getEmail(), 
                'Activation de votre compte myBlog',
                'register', 
                compact('user', 'token')
            );
        $this->addFlash('success', 'Un email de vérification vous a été renvoyé');
        
        return $this->redirectToRoute('app_main');
    }
}
