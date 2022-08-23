<?php

namespace App\Controller;

use App\Form\ResetPasswordRequestFormType;
use App\Form\ResetPasswordFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use \App\Repository\UsersRepository;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\SendMailService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Repository\UserRepository;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        /*if ($this->getUser()) {
            dd($this->getUser());
             return $this->redirectToRoute('app_main');
        }*/
        
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
    
    #[Route(path: '/forgotten-password', name:'forgotten_password')]
    public function forgottenPassword(Request $request, UserRepository $userRepo, EntityManagerInterface $em,
    SendMailService $email, TokenGeneratorInterface $tokenGenerator): Response
    {
        $form=$this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid())
        {
            $user=$userRepo->findOneByEmail($form->get('email')->getData());
            
            if($user)
            {
                // on génére le reset token à attribuer à l'utilisateur
                $token=$tokenGenerator->generateToken();
                $user->setResetToken($token);
                $em->persist($user);
                $em->flush();
                
                $url=$this->generateUrl('reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
               
                // on crée un mail à envoyer à l'utilisateur pour la réinitialisation
                $email->send(
                    'no-reply@myblog.org',
                    $user->getEmail(),
                    'Réinitialisation de votre mot de passe',
                    'reset_password',
                    compact('user', 'url')
                );
                
                $this->addFlash('success', 'Un email vous a été envoyé pour la réinitialisation du mot de passe');
                return $this->redirectToRoute('app_main');
            }
            
            $this->addFlash('danger', 'Aucun compte ne correspond à cette adresse email');
            
            return $this->redirectToRoute('app_login');
        }
        
        return $this->render('security/reset_password_request.twig', [
            'resetPasswordRequestForm' => $form->createView()
            ]);
    }
    
    #[Route('reset-password/{token}', name: 'reset_password')]
    public function resetPassword(string $token, Request $request, UserRepository $userRepo, 
            UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $em): Response
    {
        $user=$userRepo->findOneByResetToken($token);
        
        if(!$user)
        {
            $this->addFlash('danger', 'Utilisateur introuvable');
            return $this->RedirectToRoute('app_login');
        }
        
        $form=$this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);
        
        if($form->get('newPassword')->get('second')->getData() != $form->get('newPassword')->get('first')->getData())
        {
            $this->addFlash('danger', 'Confirmation erronée du mot de passe');
        }
        
        if($form->isSubmitted() && $form->isValid())
        {
            $user->setPassword(
            $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('newPassword')->getData()
                )
            );

            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success', 'Mot de passe réinitialisé avec succès');
            
            return $this->RedirectToRoute('app_main');
        }
        
        return $this->render('security/reset_password.html.twig', [
            'resetPasswordForm' => $form->createView()
        ]);
    }
}
