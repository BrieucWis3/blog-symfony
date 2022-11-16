<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ArticleRepository;
use App\Repository\KeywordRepository;
use App\Repository\UserRepository;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Article;
use App\Entity\Keyword;
use App\Entity\Image;
use App\Form\ArticleFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Environment;

class ArticleController extends AbstractController
{
    public function __construct(private SluggerInterface $slugger){}
    
    #[Route('/article', name: 'article')]
    public function Index(ArticleRepository $articleRepo): Response
    {
        // on prend les 6 premiers articles selon leurs dates, du plus récent au plus ancien
        $articles=$articleRepo->findby(array(), array('created_at' => 'DESC'), 6, null);
        
        return $this->render('article/index.html.twig', compact('articles'));
    }
    
    #[Route('/article/posted', name: 'user.article')]
    public function myarticles(ArticleRepository $articleRepo): Response 
    {
        $user=$this->getUser();
        $articles=$articleRepo->findBy(array('author' => $user));
        
        return $this->render('article/myarticles.html.twig', compact('articles', 'user'));
    }
    
    #[Route('/article/show/{slug}', name: 'show')]
    public function show(Article $article, Request $request, UserRepository $userRepo): Response
    {
        $source=$request->query->get('source');
        return $this->render('article/show.html.twig', compact('article', 'source'));
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
                'articleForm' => $form->createView(),
                'article' => $article
            ]);
    }
    
    #[Route('/article/edit/{id}', name: 'article.edit')]
    public function edit(Article $article, ArticleRepository $articleRepo, EntityManagerInterface $em, Request $request, 
            KeywordRepository $kwRepo): Response
    {
        $form=$this->createForm(ArticleFormType::class, $article);
        $form->handleRequest($request);
        $source=$request->query->get('source');
        
        if($form->isSubmitted() && $form->isValid())
        {
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
            
            /* [A OPTIMISER] méthode très hors norme pour mettre à jour les mots clés d'un article, sinon, les modifications
             * ne sont pas prises en compte. A cause de la relation n-n ? */
            
            $otherKeywords=array(); // les mots clés qui ne sont pas associés à l'article en cours d'édition
            $keywords=$kwRepo->findAll();
            foreach($keywords as $kw) // que l'on récupère ici
            {
                if(!$article->getKeywords()->contains($kw))
                {
                    array_push($otherKeywords, $kw);
                }
            }
            /* s'ils sont associés à la base à cet article, on enlève l'article concerné de la liste
            de chacun des mots clés en question */ 
            foreach($otherKeywords as $kw)
            {
                if($kw->getArticles()->contains($article))
                {
                    $kw->removeArticle($article);
                }
            }
             /* fin de la mise à jour des keyword-article*/
                    
            $em->persist($article);
            $em->flush();
            
            // on spécifie la route de redirection en fonction du template depuis lequel on a accédé à la modification
            if($source=='user')
            {
                $returnRoute='show';
            }
            else if($source=='admin')
            {
                $returnRoute='admin.article';
            }
            else
            {
                $returnRoute='article';
            }
            $this->addFlash('success', 'L\'article n°'. $article->getId() .' a été modifié avec succès');
            return $this->RedirectToRoute($returnRoute, array('slug' => $article->getSlug(), 'source' => $source));
        }
        
        return $this->render('article/edit.html.twig', [
            'articleForm' => $form->createView(),
            'article' => $article
        ]);
    }
}

