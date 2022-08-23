<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ArticleRepository;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Article;
use App\Entity\Keyword;
use App\Entity\Image;
use App\Form\ArticleFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ArticleController extends AbstractController
{
    public function __construct(private SluggerInterface $slugger){}
    
    #[Route('/article', name: 'article')]
    public function Index(ArticleRepository $articleRepo): Response
    {
        $articles=$articleRepo->findAll();
        
        return $this->render('article/index.html.twig', compact('articles'));
    }
    
    #[Route('/article/add', name: 'add_article')]
    public function add(Request $request, ArticleRepository $articleRepo, EntityManagerInterface $em): Response
    {
        $article=new Article();
        $form=$this->createForm(ArticleFormType::class, $article);
        $article->setAuthor($this->getUser());
        
        $form->handleRequest($request);
        
        if($form->isSubmitted() && $form->isValid())
        {
            // on associe à l'article le slug de son titre
            $article->setSlug($this->slugger->slug($article->getTitle())->lower());
            
            // on ajoute à notre article le thème saisis manuellement s'il y en a un
            if($form->get('textKeyword')->getData())
            {
                $keyword=new Keyword();
                $keyword->setName($form->get('textKeyword')->getData());
                $article->addKeyword($keyword);
            }
            
            // on associe chaque thème sélectionné/saisi à l'article créé
            foreach($article->getKeywords() as $keyword)
            {
                $keyword->addArticle($article);
            }
            
            // on récupère l'image transmise
            $imageFile=$form->get('image')->getData();
            // on génère un nouveau fichier
            $fichier=md5(uniqid()).'.'.$imageFile->guessExtension();
            // on copie le fichier dans le répertoire 'uploads'
            $imageFile->move($this->getParameter('image_directory'), $fichier);
            
            // on crée l'entité image correspondante
            $img=new Image();
            $img->setName($fichier);
            $article->setImage($img);
            
            $em->persist($article);
            $em->flush();
                    
            $this->addFlash('success', 'Votre article a été ajouté avec succès');        
            return $this->RedirectToROute('article');
        }
        
        return $this->render('article/edit.html.twig', [
                'articleForm' => $form->createView()
            ]);
    }
}

