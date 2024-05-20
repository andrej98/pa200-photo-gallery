<?php

namespace App\Controller;

use App\Entity\Photo;
use App\Form\PhotoType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class PhotoController extends AbstractController
{
    #[Route('/photos', name: 'photo_index')]
    public function index(EntityManagerInterface $em, Request $request): Response
    {
        $repository = $em->getRepository(Photo::class);
        $photos = $repository->findAll();

        return $this->render('photo/index.html.twig', [
            'photos' => $photos,
        ]);
    }

    #[Route('/photos/new', name: 'photo_new')]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $photo = new Photo();
        $form = $this->createForm(PhotoType::class, $photo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            $name = $form->get('name')->getData();
            $description = $form->get('description')->getData();

            if ($file) {
                // $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($name);
                // $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                // Save file to the uploads directory (you will implement the logic)
                // $file->move($this->getParameter('uploads_directory'), $newFilename);

                $url = 'https://';

                // Update the photo entity
                $photo->setName($name);
                $photo->setDescription($description);
                $photo->setUrl($url);
                $em->persist($photo);
                $em->flush();

                return $this->redirectToRoute('photo_index');
            }
        }

        return $this->render('photo/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
